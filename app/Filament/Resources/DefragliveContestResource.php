<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DefragliveContestResource\Pages;
use App\Models\DefragliveContest;
use App\Services\Comps\PrizeFunding;
use App\Services\DefragliveWatchService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DefragliveContestResource extends Resource
{
    protected static ?string $model = DefragliveContest::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationGroup = 'DefragLive';

    protected static ?string $navigationLabel = 'Watch Contests';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Contest')->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('starts_at')
                    ->seconds(false)
                    ->default(now())
                    ->helperText('Server time is UTC. Defaults to now so the contest is live immediately.')
                    ->required(),
                Forms\Components\DateTimePicker::make('ends_at')
                    ->seconds(false)
                    ->default(now()->addDays(14))
                    ->required()
                    ->after('starts_at'),
                Forms\Components\TextInput::make('prize_amount')
                    ->numeric()
                    ->default(5)
                    ->required(),
                Forms\Components\Select::make('prize_currency')
                    ->options(array_combine(
                        array_keys(DefragliveContest::CURRENCIES),
                        array_map(
                            fn ($symbol, $code) => "{$code}  {$symbol}",
                            DefragliveContest::CURRENCIES,
                            array_keys(DefragliveContest::CURRENCIES)
                        )
                    ))
                    ->default(DefragliveContest::DEFAULT_CURRENCY)
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        DefragliveContest::STATUS_DRAFT => 'Draft',
                        DefragliveContest::STATUS_ACTIVE => 'Active',
                        DefragliveContest::STATUS_CLOSED => 'Closed',
                        DefragliveContest::STATUS_PAID => 'Paid',
                        DefragliveContest::STATUS_DONATED => 'Donated to the site',
                        DefragliveContest::STATUS_FORWARDED => 'Forwarded to the next winner',
                    ])
                    ->default(DefragliveContest::STATUS_DRAFT)
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Winner (set at draw)')
                ->schema([
                    Forms\Components\TextInput::make('winner_name')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('winner_seconds')->disabled()->dehydrated(false)
                        ->suffix('seconds watched'),
                    Forms\Components\TextInput::make('winner_tickets')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('total_tickets')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('winning_ticket')->disabled()->dehydrated(false),
                    Forms\Components\Placeholder::make('draw_picks_text')
                        ->label('Drawn tickets (best of three)')
                        ->content(fn (?DefragliveContest $record) => $record?->draw_picks
                            ? implode(' | ', array_map(
                                fn ($p) => '#' . $p['ticket'] . ' ' . preg_replace('/\^[0-9A-Za-z]/', '', $p['name']) . ' (' . $p['tickets'] . ' tickets)',
                                $record->draw_picks
                            ))
                            : '-'),
                    Forms\Components\DateTimePicker::make('drawn_at')->disabled()->dehydrated(false),
                ])
                ->columns(2)
                ->visible(fn (?DefragliveContest $record) => $record?->winner_name !== null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('starts_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('starts_at')->dateTime('M j, H:i')->sortable(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime('M j, H:i')->sortable(),
                Tables\Columns\TextColumn::make('prize_amount')
                    ->formatStateUsing(fn ($state, DefragliveContest $r) => DefragliveContest::formatPrize($state, $r->prize_currency)),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        DefragliveContest::STATUS_ACTIVE => 'success',
                        DefragliveContest::STATUS_CLOSED => 'warning',
                        DefragliveContest::STATUS_PAID => 'info',
                        DefragliveContest::STATUS_DONATED => 'success',
                        DefragliveContest::STATUS_FORWARDED => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('winner_name')
                    ->placeholder('-')
                    ->formatStateUsing(fn (?string $state, DefragliveContest $r) => $state
                        ? preg_replace('/\^[0-9A-Za-z]/', '', $state) . ($r->winner_tickets ? " ({$r->winner_tickets}/{$r->total_tickets})" : '')
                        : '-'),
            ])
            ->actions([
                // Run the watch-time-weighted raffle (best of three). Available
                // once a period is closed (or active, e.g. an early draw) and
                // not yet drawn.
                Tables\Actions\Action::make('draw')
                    ->label('Draw winner')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Draw the raffle winner now? Three tickets are drawn and the holder with the most watch time wins. This sets the winner and closes the contest.')
                    ->visible(fn (DefragliveContest $r) => $r->winner_name === null)
                    ->action(function (DefragliveContest $record) {
                        $winner = app(DefragliveWatchService::class)->draw($record);
                        if (!$winner) {
                            Notification::make()->title('No eligible entrants')
                                ->body('Nobody accrued at least one ticket (1 minute watched). Contest left undrawn.')
                                ->warning()->send();

                            return;
                        }
                        $fresh = $record->fresh();
                        $picks = implode(', ', array_map(
                            fn ($p) => '#' . $p['ticket'] . ' ' . preg_replace('/\^[0-9A-Za-z]/', '', $p['name']),
                            $fresh->draw_picks ?? []
                        ));
                        Notification::make()->title('Winner drawn')
                            ->body(preg_replace('/\^[0-9A-Za-z]/', '', $winner['name']) . " - {$winner['tickets']} tickets, won with ticket {$fresh->winning_ticket} of {$fresh->total_tickets}. Drawn: {$picks}.")
                            ->success()->send();
                    }),

                // Settle a drawn contest's prize: paid out, donated back to the
                // site by the winner, or rolled over into the next contest's
                // prize pool (bump that contest's prize_amount manually).
                Tables\Actions\Action::make('resolvePrize')
                    ->label('Resolve prize')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->form([
                        Forms\Components\Radio::make('resolution')
                            ->label('How was the prize settled?')
                            ->options([
                                DefragliveContest::STATUS_PAID => 'Paid to the winner',
                                DefragliveContest::STATUS_DONATED => 'Donated to the site',
                                DefragliveContest::STATUS_FORWARDED => 'Forwarded to the next winner',
                            ])
                            ->descriptions([
                                DefragliveContest::STATUS_FORWARDED => 'Adds this prize to the next upcoming contest\'s pool, shown publicly as "carried over from previous winners".',
                            ])
                            ->default(DefragliveContest::STATUS_PAID)
                            ->required()
                            ->live(),
                        Forms\Components\Checkbox::make('create_donation')
                            ->label('Create a site donation record in the winner\'s name')
                            ->helperText('Shows up immediately in the site donations progress as approved.')
                            ->default(true)
                            ->live()
                            ->visible(fn (Forms\Get $get) => $get('resolution') === DefragliveContest::STATUS_DONATED),

                        // A winner who gives the prize back does not always
                        // mean "put it towards the hosting bill" - most of them
                        // say comps. That used to mean creating the donation
                        // here and then opening it in the donations resource to
                        // fill in three more columns by hand, which is exactly
                        // the kind of second step that gets forgotten.
                        Forms\Components\Radio::make('donation_target')
                            ->label('Where does it go?')
                            ->options([
                                'site' => 'Site upkeep',
                                'comps' => 'Comps prize pool',
                            ])
                            ->descriptions([
                                'comps' => 'Earmarked for comps, so it funds a weekly prize instead of counting towards the hosting goal.',
                            ])
                            ->default('site')
                            ->required()
                            ->live()
                            ->visible(fn (Forms\Get $get) => $get('resolution') === DefragliveContest::STATUS_DONATED
                                && $get('create_donation')),

                        Forms\Components\TextInput::make('comps_start_comp')
                            ->label('Funds weekly number')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            // The first weekly nothing else pays for. Pointing
                            // it at a week that is already funded would stack
                            // on top of that week rather than extend the pool.
                            ->default(fn () => app(PrizeFunding::class)->nextFundableComp())
                            ->helperText(fn () => 'The pool is currently paid up through weekly '
                                . (app(PrizeFunding::class)->fundedThroughComp() ?? 'nothing') . '.')
                            ->visible(fn (Forms\Get $get) => $get('resolution') === DefragliveContest::STATUS_DONATED
                                && $get('create_donation')
                                && $get('donation_target') === 'comps'),

                        Forms\Components\TextInput::make('comps_weeks')
                            ->label('Spread over how many weeklies')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required()
                            ->visible(fn (Forms\Get $get) => $get('resolution') === DefragliveContest::STATUS_DONATED
                                && $get('create_donation')
                                && $get('donation_target') === 'comps'),

                        // The comps pool has no currency logic at all: it sums
                        // the earmarked column as euro. Worth saying out loud
                        // rather than converting behind the admin's back.
                        Forms\Components\Placeholder::make('currency_warning')
                            ->hiddenLabel()
                            ->content(fn (DefragliveContest $record) => 'The comps pool is counted in EUR, but this prize is in '
                                . $record->prize_currency . '. It will be added to the pool as '
                                . $record->prize_amount . ' EUR.')
                            ->visible(fn (Forms\Get $get, DefragliveContest $record) => $record->prize_currency !== 'EUR'
                                && $get('resolution') === DefragliveContest::STATUS_DONATED
                                && $get('create_donation')
                                && $get('donation_target') === 'comps'),
                    ])
                    ->visible(fn (DefragliveContest $r) => $r->winner_name !== null
                        && ! in_array($r->status, DefragliveContest::RESOLVED_STATUSES, true))
                    ->action(function (DefragliveContest $record, array $data) {
                        $record->update(['status' => $data['resolution']]);
                        $plainWinner = trim(preg_replace('/\^[0-9A-Za-z]/', '', (string) $record->winner_name));

                        if ($data['resolution'] === DefragliveContest::STATUS_DONATED) {
                            if (empty($data['create_donation'])) {
                                Notification::make()->title('Marked as donated')
                                    ->body('No donation record created (checkbox was off).')
                                    ->success()->send();

                                return;
                            }
                            $toComps = ($data['donation_target'] ?? 'site') === 'comps';
                            $contestUrl = url('/defraglive/contest');

                            // Credit the prize back as a real site donation so it
                            // shows up in the donations progress right away.
                            // donor_email matters: isDonor()/getDonationTotal()
                            // aggregate a user's donations by email match, so
                            // without it the prize wouldn't count toward the
                            // winner's donor stats.
                            \App\Models\SiteDonation::create([
                                'user_id' => $record->winner_user_id,
                                'donor_email' => $record->winner?->email,
                                'donor_name' => $plainWinner ?: 'DefragLive contest winner',
                                'amount' => $record->prize_amount,
                                'currency' => $record->prize_currency,
                                'donation_date' => now()->toDateString(),
                                'note' => "DefragLive contest prize donated back ({$record->title}) - "
                                    . $contestUrl,
                                'status' => 'approved',
                                // The earmark. Left empty for an ordinary
                                // donation, because PrizeFunding only counts
                                // rows where all three are filled in - a
                                // half-filled one would sit in neither pot.
                                //
                                // Zero and not null: the column is NOT NULL
                                // with a default of 0, so a null threw and the
                                // prize could not be settled towards the site
                                // at all.
                                'comps_amount' => $toComps ? $record->prize_amount : 0,
                                'comps_weeks' => $toComps ? (int) $data['comps_weeks'] : null,
                                'comps_start_comp' => $toComps ? (int) $data['comps_start_comp'] : null,
                                // Public, printed under the donor's name on the
                                // comps page. The URL is written into the
                                // sentence rather than stored beside it; the
                                // page pulls it back out and makes the note
                                // itself the link.
                                'comps_note' => $toComps
                                    ? 'Donated winnings from the DefragLive contest - ' . $contestUrl
                                    : null,
                            ]);

                            $where = $toComps
                                ? "the comps prize pool, funding weekly {$data['comps_start_comp']}"
                                    . ((int) $data['comps_weeks'] > 1 ? " for {$data['comps_weeks']} weeklies" : '')
                                : 'the site upkeep';

                            Notification::make()->title('Marked as donated')
                                ->body("Site donation of {$record->prize_amount} {$record->prize_currency} recorded for {$plainWinner}, towards {$where}.")
                                ->success()->send();

                            return;
                        }

                        if ($data['resolution'] === DefragliveContest::STATUS_FORWARDED) {
                            // Roll the prize into the next contest (same currency,
                            // starting after this one; draft or active).
                            $next = DefragliveContest::query()
                                ->whereNull('winner_name')
                                ->whereKeyNot($record->getKey())
                                ->where('prize_currency', $record->prize_currency)
                                ->whereIn('status', [DefragliveContest::STATUS_DRAFT, DefragliveContest::STATUS_ACTIVE])
                                ->where('starts_at', '>=', $record->starts_at)
                                ->orderBy('starts_at')
                                ->first();

                            if ($next) {
                                $next->update([
                                    'prize_amount' => $next->prize_amount + $record->prize_amount,
                                    // Tracked separately so the public page can
                                    // show "base + carried over from previous
                                    // winners" instead of one opaque number.
                                    'carried_over_amount' => $next->carried_over_amount + $record->prize_amount,
                                ]);
                                Notification::make()->title('Marked as forwarded')
                                    ->body("Prize added to \"{$next->title}\" - its pool is now {$next->fresh()->prize_amount} {$next->prize_currency} (of which {$next->fresh()->carried_over_amount} carried over).")
                                    ->success()->send();
                            } else {
                                Notification::make()->title('Marked as forwarded - but no upcoming contest found')
                                    ->body('Create the next contest and raise its prize manually by this amount.')
                                    ->warning()->send();
                            }

                            return;
                        }

                        Notification::make()->title('Marked as paid')->success()->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDefragliveContests::route('/'),
            'create' => Pages\CreateDefragliveContest::route('/create'),
            'edit' => Pages\EditDefragliveContest::route('/{record}/edit'),
        ];
    }
}
