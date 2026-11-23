<?php

namespace App\Filament\Pages;

use App\Models\RatingSetting;
use App\Jobs\RunRatingsRecalcJob;
use App\Http\Controllers\RankingController;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

class RatingSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Rating Settings';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.rating-settings';

    // Individual setting properties
    public string $cfg_a = '';
    public string $cfg_b = '';
    public string $cfg_m = '';
    public string $cfg_v = '';
    public string $cfg_q = '';
    public string $cfg_d = '';
    public string $mult_l = '';
    public string $mult_n = '';
    public string $min_map_players = '';
    public string $min_top1_time = '';
    public string $max_tied_wr_players = '';
    public string $rank_k = '';
    public string $rank_n = '';
    public string $rank_p = '';
    public string $min_total_records = '';

    // Recalc controls
    public array $selectedPhysics = ['vq3', 'cpm'];
    public array $selectedCategories = ['overall'];

    private array $settingKeys = [
        'cfg_a', 'cfg_b', 'cfg_m', 'cfg_v', 'cfg_q', 'cfg_d',
        'mult_l', 'mult_n',
        'min_map_players', 'min_top1_time', 'max_tied_wr_players',
        'rank_k', 'rank_n', 'rank_p', 'min_total_records',
    ];

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user?->isAdmin() || $user?->hasModeratorPermission('rating_settings');
    }

    public function mount(): void
    {
        $all = RatingSetting::allAsArray();
        foreach ($this->settingKeys as $key) {
            $this->{$key} = $all[$key] ?? '';
        }
    }

    public function saveSettings(): void
    {
        foreach ($this->settingKeys as $key) {
            $this->{$key} = preg_replace('/[^0-9.\-]/', '', str_replace(',', '.', $this->{$key}));

            if (!is_numeric($this->{$key})) {
                Notification::make()
                    ->title("Invalid value for {$key}: must be a number")
                    ->danger()
                    ->send();
                return;
            }
        }

        foreach ($this->settingKeys as $key) {
            RatingSetting::set($key, $this->{$key});
        }

        Cache::forget('rating_settings');

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    public function runRecalc(): void
    {
        if (empty($this->selectedPhysics) || empty($this->selectedCategories)) {
            Notification::make()
                ->title('Select at least one physics and one category')
                ->danger()
                ->send();
            return;
        }

        $status = Cache::get('rating_recalc:status');
        if ($status === 'running') {
            Notification::make()
                ->title('Recalculation already running')
                ->warning()
                ->send();
            return;
        }

        // Set status immediately so UI starts polling
        Cache::put('rating_recalc:log', ['[' . now()->format('H:i:s') . '] Dispatched, waiting for worker...'], 86400);
        Cache::put('rating_recalc:status', 'running', 86400);

        RunRatingsRecalcJob::dispatch($this->selectedPhysics, $this->selectedCategories);

        Notification::make()
            ->title('Recalculation dispatched')
            ->body('Running in background. Progress will appear below.')
            ->success()
            ->send();
    }

    public function getRecalcLog(): array
    {
        return Cache::get('rating_recalc:log', []);
    }

    public function getRecalcStatus(): ?string
    {
        return Cache::get('rating_recalc:status');
    }

    public function clearLog(): void
    {
        Cache::forget('rating_recalc:log');
        Cache::forget('rating_recalc:status');
    }

    public function refreshCache(): void
    {
        $flushed = 0;
        $rankingTypes = ['active_players', 'all_players'];
        $categories = ['overall', 'rocket', 'plasma', 'grenade', 'slick', 'tele', 'bfg', 'strafe', 'lg'];

        foreach (['vq3', 'cpm'] as $physics) {
            foreach ($categories as $category) {
                foreach ($rankingTypes as $rankingtype) {
                    for ($page = 1; $page <= 50; $page++) {
                        Cache::forget(RankingController::pageCacheKey($physics, 'run', $rankingtype, $category, $page));
                        $flushed++;
                    }
                }
            }
        }

        Cache::forget('ranking:last_recalculation');
        Cache::forget('rating_settings');

        $controller = new RankingController();
        $rebuilt = 0;
        foreach (['vq3', 'cpm'] as $physics) {
            foreach ($categories as $category) {
                foreach ($rankingTypes as $rankingtype) {
                    for ($page = 1; $page <= RankingController::PREBUILT_PAGES; $page++) {
                        $cacheKey = RankingController::pageCacheKey($physics, 'run', $rankingtype, $category, $page);
                        $data = $controller->fetchPageFromDb($physics, 'run', $rankingtype, $category, $page);
                        Cache::put($cacheKey, $data, RankingController::CACHE_TTL);
                        $rebuilt++;
                    }
                }
            }
        }

        Notification::make()
            ->title('Cache refreshed')
            ->body("Flushed {$flushed} keys, rebuilt {$rebuilt} pages.")
            ->success()
            ->send();
    }

    public function getSettingGroups(): array
    {
        return [
            'Logistic Curve (Score)' => [
                'cfg_a' => 'Amplitude (A)',
                'cfg_b' => 'Steepness (B)',
                'cfg_m' => 'Midpoint offset (M)',
                'cfg_v' => 'Asymmetry / Richards (V)',
                'cfg_q' => 'Q parameter',
            ],
            'Hill Function (Map Multiplier)' => [
                'mult_l' => 'Max multiplier (L)',
                'mult_n' => 'Steepness (n)',
            ],
            'Weighted Average' => [
                'cfg_d' => 'Exponential decay (lower = more equal weighting)',
            ],
            'Map Eligibility' => [
                'min_map_players' => 'Min players for map to be ranked',
                'min_top1_time' => 'Min WR time in ms (filter trivial maps)',
                'max_tied_wr_players' => 'Max players sharing WR (filter free WR)',
            ],
            'Player Penalty' => [
                'min_total_records' => 'Min records before penalty applies',
            ],
            'Rank Multiplier' => [
                'rank_k' => 'Floor for worst-ranked record (k)',
                'rank_n' => 'Overall curve steepness (n)',
                'rank_p' => 'Position of steep section (p)',
            ],
        ];
    }
}
