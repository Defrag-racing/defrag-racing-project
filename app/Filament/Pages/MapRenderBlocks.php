<?php

namespace App\Filament\Pages;

use App\Models\MapRenderOverride;
use App\Models\RenderedVideo;
use App\Models\SiteSetting;
use App\Services\JokeMaps;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * The maps that get no video, and the last word over that list.
 *
 * The rule counts how many people hold a map's record time. A count is a guess
 * and it is wrong in both directions, so every map on it can be let through
 * from here, and a map the rule missed can be barred from here. Neither needs
 * the numbers moved, because moving a number moves every other map with it.
 */
class MapRenderBlocks extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationLabel = 'Blocked Maps';

    protected static ?string $navigationGroup = 'Demome';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.map-render-blocks';

    public string $tiedLimit = '3';

    public string $shortLimit = '2';

    public string $shortMs = '1000';

    public string $search = '';

    public int $page = 1;

    public int $perPage = 40;

    public string $newMap = '';

    public string $newPhysics = 'cpm';

    public function mount(): void
    {
        $this->tiedLimit = (string) JokeMaps::limit();
        $this->shortLimit = (string) JokeMaps::shortLimit();
        $this->shortMs = (string) JokeMaps::shortMs();
    }

    public function getViewData(): array
    {
        $blocked = collect(JokeMaps::detail())
            ->map(fn ($row, $key) => $row + ['key' => $key])
            ->values();

        if ($this->search !== '') {
            $needle = strtolower(trim($this->search));
            $blocked = $blocked->filter(fn ($row) => str_contains(strtolower($row['map']), $needle));
        }

        // Worst first: the more people on one time, the less of a run it is.
        $blocked = $blocked->sortByDesc('players')->values();

        $total = $blocked->count();
        $pages = max(1, (int) ceil($total / $this->perPage));
        $page = min(max(1, $this->page), $pages);

        return [
            'blocked' => $blocked->slice(($page - 1) * $this->perPage, $this->perPage)->values(),
            'blocked_total' => $total,
            'page' => $page,
            'pages' => $pages,
            'allowed' => collect(JokeMaps::allowed())->map(fn ($row, $key) => $row + ['key' => $key])->values(),
            'queued' => RenderedVideo::where('status', 'pending')->get(['id', 'map_name', 'physics'])
                ->filter(fn ($v) => JokeMaps::isJoke($v->map_name, $v->physics))->count(),
            'rendered' => RenderedVideo::whereNotNull('youtube_video_id')->get(['id', 'map_name', 'physics'])
                ->filter(fn ($v) => JokeMaps::isJoke($v->map_name, $v->physics))->count(),
        ];
    }

    public function saveLimits(): void
    {
        SiteSetting::set('demome:tied_wr_limit', (string) max(2, (int) $this->tiedLimit));
        SiteSetting::set('demome:tied_wr_short_limit', (string) max(2, (int) $this->shortLimit));
        SiteSetting::set('demome:tied_wr_short_ms', (string) max(0, (int) $this->shortMs));
        JokeMaps::forget();

        $this->mount();

        Notification::make()->title('Saved')->body('The list below is worked out again.')->success()->send();
    }

    /** Render this map after all, whatever the count says. */
    public function allow(string $key): void
    {
        [$map, $physics] = $this->split($key);

        MapRenderOverride::updateOrCreate(
            ['map_name' => $map, 'physics' => $physics],
            ['mode' => MapRenderOverride::ALLOW, 'created_by' => auth()->id()]
        );

        JokeMaps::forget();

        Notification::make()->title("{$map} ({$physics}) will be rendered again")->success()->send();
    }

    /** Bar a map the rule did not catch. */
    public function block(): void
    {
        $map = trim($this->newMap);

        if ($map === '') {
            Notification::make()->title('Type a map name first')->warning()->send();

            return;
        }

        MapRenderOverride::updateOrCreate(
            ['map_name' => $map, 'physics' => strtolower($this->newPhysics)],
            ['mode' => MapRenderOverride::BLOCK, 'created_by' => auth()->id()]
        );

        JokeMaps::forget();
        $this->newMap = '';

        Notification::make()->title("{$map} ({$this->newPhysics}) gets no video")->success()->send();
    }

    /** Undo a decision made here, and let the rule speak again. */
    public function revoke(string $key): void
    {
        [$map, $physics] = $this->split($key);

        MapRenderOverride::where('map_name', $map)->where('physics', $physics)->delete();
        JokeMaps::forget();

        Notification::make()->title("{$map} ({$physics}) follows the rule again")->success()->send();
    }

    /**
     * Drop the queued renders for barred maps. Only what has not been rendered:
     * a video already on the channel is somebody's upload and is not thrown
     * away from a page about what to render next.
     */
    public function cleanQueue(): void
    {
        $ids = RenderedVideo::where('status', 'pending')->get(['id', 'map_name', 'physics'])
            ->filter(fn ($v) => JokeMaps::isJoke($v->map_name, $v->physics))
            ->pluck('id');

        $dropped = RenderedVideo::whereIn('id', $ids)->delete();

        Notification::make()
            ->title("Dropped {$dropped} queued render(s)")
            ->body('Videos already on YouTube were left alone.')
            ->success()
            ->send();
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    /** @return array{0: string, 1: string} */
    private function split(string $key): array
    {
        $parts = explode('|', $key, 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }
}
