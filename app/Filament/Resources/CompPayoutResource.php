<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompPayoutResource\Pages;
use App\Models\CompPayout;
use App\Services\Comps\PrizeFunding;
use App\Services\Comps\PrizePayouts;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * The money side of comps: who won a week, what it was worth, and whether it
 * has been settled.
 *
 * A prize has three endings and only one of them is a transfer. The winner
 * takes it, gives it to the site, or puts it back into the pool for the weeks
 * to come. The last two are donations like any other and are written as such,
 * so somebody who never takes a euro out still shows up as a donor - which is
 * the honest reading of what they did.
 *
 * Rows appear on their own when a round finishes. Nothing here is created by
 * hand, because a prize that nobody won is not a thing.
 */
class CompPayoutResource extends Resource
{
    protected static ?string $model = CompPayout::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Comps: prizes';

    protected static ?string $navigationGroup = 'Comps';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /** How many weeks are still owed, on the menu item itself. */
    public static function getNavigationBadge(): ?string
    {
        $pending = CompPayout::pending()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('round.comp.title')
                    ->label('Comp')
                    ->size('xs')
                    ->sortable(),

                Tables\Columns\TextColumn::make('physics')
                    ->badge()
                    ->size('xs')
                    ->color(fn (string $state) => $state === 'cpm' ? 'info' : 'success')
                    ->formatStateUsing(fn ($state) => strtoupper($state)),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Winner')
                    ->searchable()
                    ->weight('bold')
                    ->size('xs')
                    ->formatStateUsing(fn (?string $state): string => $state ? UserResource::q3tohtml($state) : '-')
                    ->html(),

                // The address the money is actually sent to. Shown because
                // settling a prize means writing to somebody, and having to
                // open the user in another tab for it is the step that gets
                // skipped.
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->size('xs')
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('amount')
                    ->size('xs')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2) . ' EUR')
                    // Where a split went, under the total. One-way rows say
                    // it in the status already and get nothing here.
                    ->description(fn (CompPayout $r) => $r->status === CompPayout::STATUS_SPLIT
                        ? implode(' · ', array_map(
                            fn ($status, $eur) => number_format($eur, 2) . ' ' . strtolower(CompPayout::LABELS[$status]),
                            array_keys($r->parts()),
                            $r->parts()
                        ))
                        : null),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->size('xs')
                    ->color(fn (string $state) => match ($state) {
                        CompPayout::STATUS_PENDING => 'warning',
                        CompPayout::STATUS_PAID => 'info',
                        CompPayout::STATUS_SPLIT => 'primary',
                        default => 'success',
                    })
                    ->formatStateUsing(fn (string $state) => CompPayout::LABELS[$state] ?? $state),

                Tables\Columns\TextColumn::make('resolved_at')
                    ->label('Settled')
                    ->dateTime('M j, H:i')
                    ->size('xs')
                    ->placeholder('-')
                    ->description(fn (CompPayout $r) => $r->note)
                    ->sortable(),

                // Where a given-back prize landed. A number rather than a
                // sentence: it is there to be looked up in the donations, and
                // the sentence is already in the status.
                Tables\Columns\TextColumn::make('site_donation_id')
                    ->label('Donation')
                    ->size('xs')
                    ->placeholder('-')
                    ->getStateUsing(fn (CompPayout $r) => implode(' ', array_filter([
                        $r->site_donation_id ? '#' . $r->site_donation_id : null,
                        $r->comps_donation_id ? '#' . $r->comps_donation_id : null,
                    ])) ?: null)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(CompPayout::LABELS),

                Tables\Filters\SelectFilter::make('physics')
                    ->options(['cpm' => 'CPM', 'vq3' => 'VQ3']),
            ])
            ->actions([
                Tables\Actions\Action::make('resolvePrize')
                    ->label('Settle')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->modalHeading('How was this prize settled?')
                    ->modalSubmitActionLabel('Settle')
                    ->visible(fn (CompPayout $r) => ! $r->isResolved())
                    ->form([
                        Forms\Components\Placeholder::make('who')
                            ->hiddenLabel()
                            ->content(fn (CompPayout $r) => new \Illuminate\Support\HtmlString(
                                UserResource::q3tohtml((string) $r->user?->name)
                                . ' won ' . strtoupper($r->physics) . ' in ' . e($r->round?->comp?->title ?? 'this round')
                                . ' and is owed ' . number_format((float) $r->amount, 2) . ' EUR'
                                . ($r->user?->email ? ' (' . e($r->user->email) . ')' : '')
                            )),

                        Forms\Components\Radio::make('resolution')
                            ->hiddenLabel()
                            ->options([
                                CompPayout::STATUS_PAID => CompPayout::LABELS[CompPayout::STATUS_PAID],
                                CompPayout::STATUS_DONATED_SITE => CompPayout::LABELS[CompPayout::STATUS_DONATED_SITE],
                                CompPayout::STATUS_DONATED_COMPS => CompPayout::LABELS[CompPayout::STATUS_DONATED_COMPS],
                                CompPayout::STATUS_SPLIT => 'Split it',
                            ])
                            ->descriptions([
                                CompPayout::STATUS_DONATED_SITE => 'Records an approved site donation in the winner\'s name, counting towards the hosting goal.',
                                CompPayout::STATUS_DONATED_COMPS => 'Records the same donation earmarked for comps, so it pays a later weekly instead.',
                                CompPayout::STATUS_SPLIT => 'Some paid out, the rest given back. The three parts must add up to the prize.',
                            ])
                            ->default(CompPayout::STATUS_PAID)
                            ->required()
                            ->live(),

                        // The three parts of a split. Each one is a plain
                        // euro figure; the sum is checked on the paid field so
                        // the complaint sits next to the numbers.
                        Forms\Components\Grid::make(3)
                            ->visible(fn (Forms\Get $get) => $get('resolution') === CompPayout::STATUS_SPLIT)
                            ->schema([
                                Forms\Components\TextInput::make('split_paid')
                                    ->label('Paid out')
                                    ->numeric()->minValue(0)->step(0.01)->suffix('EUR')
                                    ->default(fn (CompPayout $record) => (float) $record->amount)
                                    ->live(onBlur: true)
                                    ->rules([
                                        fn (CompPayout $record, Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($record, $get) {
                                            $sum = (float) $get('split_paid') + (float) $get('split_site') + (float) $get('split_comps');

                                            if (abs($sum - (float) $record->amount) > 0.005) {
                                                $fail(sprintf("The parts add up to %.2f EUR, the prize is %.2f EUR.", $sum, (float) $record->amount));
                                            }
                                        },
                                    ]),
                                // Type what was given back; the paid-out part
                                // fills itself with whatever is left of the
                                // prize. Three free fields that had to add up
                                // read as if the form could not split at all.
                                Forms\Components\TextInput::make('split_site')
                                    ->label('To the website')
                                    ->numeric()->minValue(0)->step(0.01)->suffix('EUR')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (CompPayout $record, Forms\Get $get, Forms\Set $set) => self::fillPaidRemainder($record, $get, $set)),
                                Forms\Components\TextInput::make('split_comps')
                                    ->label('To the next comps')
                                    ->numeric()->minValue(0)->step(0.01)->suffix('EUR')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (CompPayout $record, Forms\Get $get, Forms\Set $set) => self::fillPaidRemainder($record, $get, $set)),
                            ]),
                        Forms\Components\Placeholder::make('split_hint')
                            ->hiddenLabel()
                            ->visible(fn (Forms\Get $get) => $get('resolution') === CompPayout::STATUS_SPLIT)
                            ->content('Type what was given back to the website or to comps. Paid out fills itself with the rest of the prize.'),

                        Forms\Components\TextInput::make('comps_start_comp')
                            ->label('Funds weekly number')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            // The first weekly nothing else pays for. Pointing
                            // it at a funded week stacks on top of that week
                            // rather than extending the pool.
                            ->default(fn () => app(PrizeFunding::class)->nextFundableComp())
                            ->helperText(fn () => 'The pool is currently paid up through weekly '
                                . (app(PrizeFunding::class)->fundedThroughComp() ?? 'nothing') . '.')
                            ->visible(fn (Forms\Get $get) => $get('resolution') === CompPayout::STATUS_DONATED_COMPS
                                || ($get('resolution') === CompPayout::STATUS_SPLIT && (float) $get('split_comps') > 0)),

                        Forms\Components\TextInput::make('comps_weeks')
                            ->label('Spread over how many weeklies')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required()
                            ->visible(fn (Forms\Get $get) => $get('resolution') === CompPayout::STATUS_DONATED_COMPS
                                || ($get('resolution') === CompPayout::STATUS_SPLIT && (float) $get('split_comps') > 0)),

                        Forms\Components\TextInput::make('note')
                            ->label('Note')
                            ->maxLength(255)
                            ->helperText('Admin only. The transfer reference, or why it went where it went.'),
                    ])
                    ->action(function (CompPayout $record, array $data) {
                        $payouts = app(PrizePayouts::class);

                        try {
                            $payout = $data['resolution'] === CompPayout::STATUS_SPLIT
                                ? $payouts->settle($record, [
                                    CompPayout::STATUS_PAID => (float) ($data['split_paid'] ?? 0),
                                    CompPayout::STATUS_DONATED_SITE => (float) ($data['split_site'] ?? 0),
                                    CompPayout::STATUS_DONATED_COMPS => (float) ($data['split_comps'] ?? 0),
                                ], $data)
                                : $payouts->resolve($record, $data['resolution'], $data);
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()->danger()->title('Not settled')->body($e->getMessage())->send();

                            return;
                        }

                        $lines = [];

                        foreach ($payout->parts() as $status => $eur) {
                            $lines[] = number_format($eur, 2) . ' EUR ' . strtolower(CompPayout::LABELS[$status]);
                        }

                        $donations = array_filter([$payout->site_donation_id, $payout->comps_donation_id]);

                        Notification::make()
                            ->success()
                            ->title($payout->label())
                            ->body(implode(' · ', $lines)
                                . ($donations ? ' Donation ' . implode(', ', array_map(fn ($id) => "#{$id}", $donations)) . ' recorded.' : ''))
                            ->send();
                    }),

                // Settling the wrong row happens, and the only thing worse
                // than that is having no way back. The donation it wrote is
                // left alone on purpose: deleting money out from under the
                // donations list is a bigger surprise than an admin removing
                // one row by hand.
                Tables\Actions\Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Puts this prize back on the owed list. Any donation it created stays where it is and has to be removed by hand.')
                    ->visible(fn (CompPayout $r) => $r->isResolved())
                    ->action(function (CompPayout $record) {
                        $record->update([
                            'status' => CompPayout::STATUS_PENDING,
                            'paid_eur' => 0,
                            'donated_site_eur' => 0,
                            'donated_comps_eur' => 0,
                            'site_donation_id' => null,
                            'comps_donation_id' => null,
                            'resolved_at' => null,
                            'resolved_by' => null,
                        ]);

                        Notification::make()->title('Back on the owed list')->success()->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompPayouts::route('/'),
        ];
    }

    /** Paid out = prize minus what was given back, never below zero. */
    private static function fillPaidRemainder(CompPayout $record, Forms\Get $get, Forms\Set $set): void
    {
        $rest = (float) $record->amount - (float) $get('split_site') - (float) $get('split_comps');
        $set('split_paid', number_format(max(0, $rest), 2, '.', ''));
    }
}
