<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserAliasResource\Pages;
use App\Models\UserAlias;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class UserAliasResource extends Resource
{
    protected static ?string $model = UserAlias::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'User Aliases';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Alias Information')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->required(),

                        Forms\Components\TextInput::make('alias')
                            ->label('Alias')
                            ->required()
                            ->maxLength(255)
                            ->regex('/^[^^]+$/')
                            ->helperText('Aliases cannot contain Quake 3 color codes (^).'),

                        Forms\Components\TextInput::make('mdd_id')
                            ->label('mDd ID')
                            ->numeric()
                            ->nullable(),

                        Forms\Components\TextInput::make('alias_colored')
                            ->label('Colored Alias')
                            ->nullable(),

                        Forms\Components\TextInput::make('source')
                            ->label('Source')
                            ->default('manual')
                            ->disabled(),

                        Forms\Components\Toggle::make('is_approved')
                            ->label('Approved')
                            ->default(false),
                    ])->columns(3),

                Forms\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Created At')
                            ->content(fn ($record) => $record?->created_at?->format('M d, Y H:i:s') ?? '-'),

                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Updated At')
                            ->content(fn ($record) => $record?->updated_at?->format('M d, Y H:i:s') ?? '-'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => \App\Filament\Resources\UserResource::q3tohtml($state))
                    ->html(),

                Tables\Columns\TextColumn::make('alias')
                    ->label('Alias')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('mdd_id')
                    ->label('mDd ID')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->color(fn ($state) => $state === 'mdd_import' ? 'info' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('usage_count')
                    ->label('Usage')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_approved')
                    ->label('Approved')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reports_count')
                    ->label('Reports')
                    ->counts('reports')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'secondary'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->since(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_approved')
                    ->label('Approval Status')
                    ->placeholder('All aliases')
                    ->trueLabel('Approved only')
                    ->falseLabel('Pending only'),

                Tables\Filters\Filter::make('has_reports')
                    ->label('Has Reports')
                    ->query(fn (Builder $query): Builder => $query->has('reports')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (UserAlias $record) => !$record->is_approved)
                    ->requiresConfirmation()
                    ->modalHeading('Approve Alias')
                    ->modalDescription(fn (UserAlias $record) =>
                        "Approve alias '{$record->alias}' for user {$record->user->name}?"
                    )
                    ->action(function (UserAlias $record) {
                        $record->update(['is_approved' => true]);

                        Notification::make()
                            ->title('Alias Approved')
                            ->body('Demos will be rematched during the next scheduled run.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (UserAlias $record) => $record->is_approved)
                    ->requiresConfirmation()
                    ->modalHeading('Reject Alias')
                    ->modalDescription(fn (UserAlias $record) =>
                        "Reject alias '{$record->alias}' for user {$record->user->name}?"
                    )
                    ->action(function (UserAlias $record) {
                        $record->update(['is_approved' => false]);

                        Notification::make()
                            ->title('Alias Rejected')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Delete')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_approved' => true]);
                            }

                            Notification::make()
                                ->title('Aliases Approved')
                                ->body('Demos will be rematched during the next scheduled run.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('reject')
                        ->label('Reject Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update(['is_approved' => false]);

                            Notification::make()
                                ->title('Aliases Rejected')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserAliases::route('/'),
            'create' => Pages\CreateUserAlias::route('/create'),
            'edit' => Pages\EditUserAlias::route('/{record}/edit'),
            'view' => Pages\ViewUserAlias::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_approved', false)->count() ?: null;
    }
}
