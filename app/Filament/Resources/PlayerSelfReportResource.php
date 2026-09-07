<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlayerSelfReportResource\Pages;
use App\Http\Controllers\RecordFlagController;
use App\Models\PlayerSelfReport;
use App\Models\Record;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Times players took down themselves, and the one way back.
 *
 * Nothing here needs approving - the amnesty is unconditional and that is what
 * makes people use it. The reason this list exists at all is the mistake case:
 * somebody withdraws the wrong run, and without a button the only fix is a
 * hand-written database update.
 *
 * Restoring undoes both halves, the record and the log entry, because a
 * withdrawal that was a misclick did not happen and the public log should not
 * claim it did.
 *
 * TWO LEVELS. The index is one row per player - how many runs, how many
 * different reasons, how much is still open - and the runs themselves live one
 * click deeper. Withdrawals arrive in batches, so a flat list of runs is a list
 * where the same person appears six times and the shape of what they sent is
 * invisible.
 */
class PlayerSelfReportResource extends Resource
{
    protected static ?string $model = PlayerSelfReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationGroup = 'Validators';

    protected static ?string $navigationLabel = 'Withdrawn times (private)';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** Requests nobody has acted on yet. */
    public static function getNavigationBadge(): ?string
    {
        return (string) PlayerSelfReport::pending()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** The name of one player, for the heading of their page. */
    public static function playerName(?string $mdd): string
    {
        $name = PlayerSelfReport::where('mdd_id', $mdd)->value('player_name');

        return trim(preg_replace('/\^[0-9A-Za-z]/', '', $name ?? '')) ?: ('mDd #' . $mdd);
    }

    /**
     * The index: one row per player.
     *
     * Grouped in SQL, with min(id) kept as the key so every row still has a
     * real primary key for Filament to hang actions off.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->heading('Who has used the amnesty')
            ->description('One row per player. Open a player to see the runs they sent and act on them.')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->selectRaw('min(id) as id, mdd_id, max(user_id) as user_id, max(player_name) as player_name')
                ->selectRaw('count(*) as runs')
                ->selectRaw('count(distinct reason) as reason_count')
                // The reasons themselves come out of the same aggregate. They
                // used to be a second query memoised in a static, which under
                // Octane outlives the request and freezes on whatever the
                // worker saw first.
                ->selectRaw("group_concat(distinct reason order by reason separator ',') as reason_list")
                // Open splits in two, because the two halves are different
                // jobs: one is waiting on an admin, the other is waiting on
                // the MDD merge and needs nobody.
                ->selectRaw("sum(processed_at is null and handling = 'immediate') as waiting_count")
                ->selectRaw("sum(processed_at is null and handling = 'on_merge') as queued_count")
                ->selectRaw("sum(processed_at is not null and coalesce(resolution, '') not in ('beaten', 'restored')) as hidden_count")
                ->selectRaw("sum(coalesce(resolution, '') = 'beaten') as beaten_count")
                ->selectRaw("sum(coalesce(resolution, '') = 'restored') as restored_count")
                ->selectRaw('max(created_at) as last_request')
                ->with('user:id,name,plain_name,amnesty_blocked_at')
                ->groupBy('mdd_id')
                // Whoever is waiting on a decision comes first, whatever the sort.
                ->orderByRaw('sum(processed_at is null) > 0 desc'))
            ->defaultSort('last_request', 'desc')
            ->filters([
                Tables\Filters\Filter::make('waiting')
                    ->label('Waiting on you')
                    ->query(fn (Builder $query) => $query->havingRaw("sum(processed_at is null and handling = 'immediate') > 0")),

                Tables\Filters\Filter::make('queued')
                    ->label('Queued for the merge')
                    ->query(fn (Builder $query) => $query->havingRaw("sum(processed_at is null and handling = 'on_merge') > 0")),

                Tables\Filters\Filter::make('blocked')
                    ->label('Blocked from the amnesty')
                    ->query(fn (Builder $query) => $query->whereHas('user', fn ($q) => $q->whereNotNull('amnesty_blocked_at'))),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('player_name')
                    ->label('Player')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state) => UserResource::q3tohtml($state ?? ''))
                    ->html()
                    ->description(fn (PlayerSelfReport $record) => $record->user?->amnesty_blocked_at
                        ? 'BLOCKED from the amnesty'
                        : ($record->mdd_id ? 'MDD #' . $record->mdd_id : null))
                    ->color(fn (PlayerSelfReport $record) => $record->user?->amnesty_blocked_at ? 'danger' : null),

                Tables\Columns\TextColumn::make('runs')
                    ->label('Runs')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                // The count is the number worth sorting on; the reasons
                // themselves say whether it was one mistake repeated or a
                // scattering of different ones.
                Tables\Columns\TextColumn::make('reason_count')
                    ->label('Reasons')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->description(function (PlayerSelfReport $record) {
                        $reasons = array_filter(explode(',', (string) $record->reason_list));
                        $labels = array_map(fn ($r) => RecordFlagController::flagTypes()[$r] ?? $r, $reasons);

                        return implode(', ', array_slice($labels, 0, 3))
                            . (count($labels) > 3 ? ' +' . (count($labels) - 3) . ' more' : '');
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('waiting_count')
                    ->label('Waiting on you')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                    ->sortable()
                    ->tooltip('Asked to be hidden now'),

                Tables\Columns\TextColumn::make('queued_count')
                    ->label('Queued')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'info' : 'gray')
                    ->sortable()
                    ->tooltip('Waiting for the mDd merge - nothing to do until then'),

                Tables\Columns\TextColumn::make('hidden_count')
                    ->label('Hidden')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('beaten_count')
                    ->label('Beaten')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'info' : 'gray')
                    ->sortable()
                    ->tooltip('Closed by the player beating the time themselves'),

                Tables\Columns\TextColumn::make('restored_count')
                    ->label('Put back')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->sortable()
                    ->tooltip('Hidden and then put back on the board by an admin'),

                Tables\Columns\TextColumn::make('last_request')
                    ->label('Last request')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-right')
                    ->url(fn (PlayerSelfReport $record) => Pages\PlayerAmnesty::getUrl(['mdd' => $record->mdd_id])),

                static::blockAction(),
            ]);
    }

    /**
     * One player's runs. Same rows as before, minus the player column - the
     * heading already says whose page this is.
     */
    public static function detailTable(Table $table, ?string $mdd): Table
    {
        return $table
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('mdd_id', $mdd)
                ->with('user:id,name,plain_name,amnesty_blocked_at')
                ->orderByRaw('processed_at is null desc'))
            ->groups([
                Tables\Grouping\Group::make('reason')
                    ->label('Reason')
                    ->collapsible()
                    ->getTitleFromRecordUsing(fn (PlayerSelfReport $record) => RecordFlagController::flagTypes()[$record->reason] ?? $record->reason),
                Tables\Grouping\Group::make('mapname')
                    ->label('Map')
                    ->collapsible(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('state')
                    ->label('State')
                    ->options([
                        'waiting' => 'Waiting on you',
                        'queued' => 'Queued for the merge',
                        'open' => 'Anything still open',
                        'hidden' => 'Off the board',
                        'beaten' => 'Beaten - resolved',
                        'restored' => 'Put back',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'waiting' => $query->whereNull('processed_at')->where('handling', 'immediate'),
                        'queued' => $query->whereNull('processed_at')->where('handling', 'on_merge'),
                        'open' => $query->whereNull('processed_at'),
                        'hidden' => $query->whereNotNull('processed_at')->where(fn ($q) => $q->whereNull('resolution')->orWhereNotIn('resolution', ['beaten', 'restored'])),
                        'beaten' => $query->where('resolution', 'beaten'),
                        'restored' => $query->where('resolution', 'restored'),
                        default => $query,
                    }),

                Tables\Filters\SelectFilter::make('reason')
                    ->label('Reason')
                    ->multiple()
                    ->options(RecordFlagController::flagTypes()),

                Tables\Filters\SelectFilter::make('physics')
                    ->options(['cpm' => 'CPM', 'vq3' => 'VQ3']),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('mapname')
                    ->label('Map')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('physics')
                    ->label('Physics')
                    ->formatStateUsing(fn (?string $state, PlayerSelfReport $record) => trim(($state ?? '') . ' ' . ($record->mode ?? ''))),

                Tables\Columns\TextColumn::make('time')
                    ->label('Time'),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => RecordFlagController::flagTypes()[$state] ?? $state),

                Tables\Columns\TextColumn::make('processed_at')
                    ->label('State')
                    ->badge()
                    ->state(fn (PlayerSelfReport $record) => match (true) {
                        $record->wasBeaten() => 'Beaten - resolved',
                        $record->wasRestored() => 'Put back',
                        $record->isProcessed() => 'Off the board',
                        $record->handling === 'immediate' => 'Waiting on you',
                        default => 'Queued for the merge',
                    })
                    ->color(fn (PlayerSelfReport $record) => match (true) {
                        $record->wasBeaten() => 'info',
                        $record->wasRestored() => 'danger',
                        $record->isProcessed() => 'success',
                        $record->handling === 'immediate' => 'warning',
                        default => 'gray',
                    }),

                // What evidence exists for this run, before deciding anything
                // about it. A withdrawal with a serverdemo behind it can be
                // checked; one with nothing cannot, and that is worth seeing
                // at a glance rather than by clicking every row.
                Tables\Columns\TextColumn::make('evidence')
                    ->label('Available')
                    ->badge()
                    ->state(function (PlayerSelfReport $record) {
                        $has = [];

                        if ($record->serverDemo()) {
                            $has[] = 'Serverdemo';
                        }

                        if ($record->uploadedDemo()) {
                            $has[] = 'Public demo';
                        }

                        if ($record->video()) {
                            $has[] = 'Video';
                        }

                        return $has ?: ['Nothing'];
                    })
                    ->color(fn (string $state) => $state === 'Nothing' ? 'gray' : 'success'),

                Tables\Columns\TextColumn::make('note')
                    ->label('Note')
                    ->wrap()
                    ->limit(120),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Withdrawn')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('serverdemo')
                    ->label('Serverdemo')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn (PlayerSelfReport $record) => $record->serverDemo() !== null)
                    ->url(fn (PlayerSelfReport $record) => \Illuminate\Support\Facades\URL::signedRoute(
                        'defraghq.amnesty-demo',
                        ['report' => $record->getKey()],
                        now()->addHour(),
                    ), shouldOpenInNewTab: true),

                Tables\Actions\Action::make('publicdemo')
                    ->label('Public demo')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn (PlayerSelfReport $record) => $record->uploadedDemo() !== null)
                    ->url(fn (PlayerSelfReport $record) => route('demos.download', $record->uploadedDemo()), shouldOpenInNewTab: true),

                Tables\Actions\Action::make('video')
                    ->label('Watch on YouTube')
                    ->icon('heroicon-o-play')
                    ->color('danger')
                    ->visible(fn (PlayerSelfReport $record) => $record->video() !== null)
                    ->url(fn (PlayerSelfReport $record) => $record->video()->youtube_url, shouldOpenInNewTab: true),

                // The hide itself. Nothing leaves the leaderboard until this
                // is pressed, which is also what the player was promised.
                Tables\Actions\Action::make('hide')
                    ->label('Approve and hide the run')
                    ->icon('heroicon-o-eye-slash')
                    ->color('success')
                    // Offered again after a restore: putting a run back can
                    // itself be the mistake, and then there is no way forward.
                    ->visible(fn (PlayerSelfReport $record) => $record->record_id
                        && (! $record->isProcessed() || $record->wasRestored())
                        && Record::whereKey($record->record_id)->exists())
                    ->requiresConfirmation()
                    ->modalDescription('Takes the run off the leaderboard here. Until the mDd databases are merged it still stands on q3df.org.')
                    ->action(function (PlayerSelfReport $record) {
                        $record->hideRun(auth()->id());

                        Notification::make()->success()->title('Run hidden')->send();
                    }),

                Tables\Actions\Action::make('restore')
                    ->label('Put the run back')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Puts the record back on the leaderboard, with its demo and video, and drops the request. Use it when the wrong run was sent.')
                    ->visible(fn (PlayerSelfReport $record) => $record->hidRun()
                        && Record::onlyTrashed()->whereKey($record->record_id)->exists())
                    ->action(function (PlayerSelfReport $record) {
                        $record->restoreRun(auth()->id());

                        Notification::make()->success()->title('Run restored')->send();
                    }),
            ])
            // A batch arrives as a batch and is usually decided as one. Doing
            // six of them one modal at a time is where the seventh gets missed.
            ->bulkActions([
                Tables\Actions\BulkAction::make('hideSelected')
                    ->label('Approve and hide the selected runs')
                    ->icon('heroicon-o-eye-slash')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Takes every selected run off the leaderboard here. Until the mDd databases are merged they still stand on q3df.org.')
                    ->deselectRecordsAfterCompletion()
                    ->action(function ($records) {
                        $done = 0;

                        foreach ($records as $record) {
                            if ($record->isProcessed() || ! $record->record_id) {
                                continue;
                            }

                            if ($record->hideRun(auth()->id())) {
                                $done++;
                            }
                        }

                        Notification::make()->success()
                            ->title($done . ' ' . ($done === 1 ? 'run' : 'runs') . ' hidden')
                            ->send();
                    }),

                Tables\Actions\BulkAction::make('restoreSelected')
                    ->label('Put the selected runs back')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Puts the records back on the leaderboard and drops the requests.')
                    ->deselectRecordsAfterCompletion()
                    ->action(function ($records) {
                        $done = 0;

                        foreach ($records as $record) {
                            if ($record->restoreRun(auth()->id())) {
                                $done++;
                            }
                        }

                        Notification::make()->success()
                            ->title($done . ' ' . ($done === 1 ? 'run' : 'runs') . ' restored')
                            ->send();
                    }),
            ]);
    }

    /**
     * Abuse is spotted here and nowhere else, so the switch that answers it
     * lives here too. It does not touch the withdrawal it was noticed on:
     * taking a run back and taking the right away are separate decisions.
     */
    public static function blockAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('block')
            ->label(fn (PlayerSelfReport $record) => $record->user?->amnesty_blocked_at
                ? 'Give the amnesty back'
                : 'Block from the amnesty')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (PlayerSelfReport $record) => $record->user !== null)
            ->requiresConfirmation()
            ->form(fn (PlayerSelfReport $record) => $record->user?->amnesty_blocked_at ? [] : [
                \Filament\Forms\Components\TextInput::make('reason')
                    ->label('Reason (shown to the player)')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (PlayerSelfReport $record, array $data) {
                $user = $record->user;

                if (! $user) {
                    return;
                }

                if ($user->amnesty_blocked_at) {
                    $user->amnesty_blocked_at = null;
                    $user->amnesty_blocked_reason = null;
                    $user->save();

                    Notification::make()->success()->title('Amnesty restored')->send();

                    return;
                }

                $user->amnesty_blocked_at = now();
                $user->amnesty_blocked_reason = $data['reason'] ?? null;
                $user->save();

                Notification::make()->success()->title('Player blocked from the amnesty')->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlayerSelfReports::route('/'),
            'player' => Pages\PlayerAmnesty::route('/{mdd}'),
        ];
    }
}
