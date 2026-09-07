<?php

namespace App\Filament\Resources\PlayerSelfReportResource\Pages;

use App\Filament\Resources\PlayerSelfReportResource;
use App\Models\PlayerSelfReport;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * One player's withdrawals.
 *
 * The mdd id is held in a public property rather than read from the route on
 * every call: Livewire's follow-up requests go to its own endpoint, where the
 * route parameters of this page do not exist.
 */
class PlayerAmnesty extends ListRecords
{
    protected static string $resource = PlayerSelfReportResource::class;

    public ?string $mdd = null;

    public function mount(): void
    {
        $this->mdd = request()->route('mdd');

        parent::mount();
    }

    public function getMaxContentWidth(): string | null
    {
        return 'full';
    }

    public function getTitle(): string
    {
        return PlayerSelfReportResource::playerName($this->mdd);
    }

    public function getSubheading(): ?string
    {
        return 'mDd #' . $this->mdd . ' - everything this player took down';
    }

    public function table(Table $table): Table
    {
        return PlayerSelfReportResource::detailTable($table, $this->mdd);
    }

    /**
     * The three things a request can be, in the order they happen.
     *
     * Waiting is work; queued is not work until the databases merge; done is
     * over. Keeping them in one list means the four runs that need nothing
     * from you sit among the ones that do.
     */
    public function getTabs(): array
    {
        $count = fn (\Closure $scope) => $scope(PlayerSelfReport::where('mdd_id', $this->mdd))->count() ?: null;

        $waiting = fn (Builder $query) => $query
            ->whereNull('processed_at')->where('handling', 'immediate');

        $queued = fn (Builder $query) => $query
            ->whereNull('processed_at')->where('handling', 'on_merge');

        // Done is what stayed down. A run that was put back is the opposite of
        // done, so it gets its own tab rather than sitting among the settled.
        $done = fn (Builder $query) => $query
            ->whereNotNull('processed_at')
            ->where(fn ($q) => $q->whereNull('resolution')->orWhere('resolution', '!=', 'restored'));

        $restored = fn (Builder $query) => $query->where('resolution', 'restored');

        return [
            'waiting' => Tab::make('Waiting on you')
                ->modifyQueryUsing($waiting)
                ->badge(fn () => $count($waiting))
                ->badgeColor('warning'),

            'queued' => Tab::make('Queued for the merge')
                ->modifyQueryUsing($queued)
                ->badge(fn () => $count($queued))
                ->badgeColor('info'),

            'done' => Tab::make('Done')
                ->modifyQueryUsing($done)
                ->badge(fn () => $count($done))
                ->badgeColor('success'),

            'restored' => Tab::make('Put back')
                ->modifyQueryUsing($restored)
                ->badge(fn () => $count($restored))
                ->badgeColor('danger'),

            'all' => Tab::make('All'),
        ];
    }

    /**
     * Open on the first tab that has anything in it. A player whose requests
     * are all settled would otherwise land on an empty Waiting tab, which
     * reads as the page having lost them.
     */
    public function getDefaultActiveTab(): string | int | null
    {
        $base = fn () => PlayerSelfReport::where('mdd_id', $this->mdd);

        if ((clone $base())->whereNull('processed_at')->where('handling', 'immediate')->exists()) {
            return 'waiting';
        }

        if ((clone $base())->whereNull('processed_at')->exists()) {
            return 'queued';
        }

        if ((clone $base())->whereNotNull('processed_at')
            ->where(fn ($q) => $q->whereNull('resolution')->orWhere('resolution', '!=', 'restored'))
            ->exists()) {
            return 'done';
        }

        return 'restored';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to the list of players')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(PlayerSelfReportResource::getUrl('index')),
        ];
    }
}
