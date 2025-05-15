<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServerResource\Pages;
use App\Filament\Resources\ServerResource\RelationManagers;
use App\Models\Server;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use Filament\Forms\Components\Group;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class ServerResource extends Resource
{
    protected static ?string $model = Server::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static bool $shouldSkipAuthorization = true;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('ip')
                    ->required()
                    ->maxLength(255),
                TextInput::make('port')
                    ->required()
                    ->numeric(),
                TextInput::make('location')
                    ->required()
                    ->maxLength(255),
                TextInput::make('type')
                    ->required()
                    ->maxLength(255),
                TextInput::make('admin_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('admin_contact')
                    ->maxLength(255),
                TextInput::make('ping_url')
                    ->maxLength(255),
                Toggle::make('online')
                    ->required(),
                Toggle::make('visible')
                    ->required(),
                TextInput::make('map')
                    ->required()
                    ->maxLength(255),
                TextInput::make('defrag')
                    ->required()
                    ->maxLength(255),
                TextInput::make('defrag_gametype')
                    ->required()
                    ->maxLength(255)
                    ->default(5),
                Group::make()->schema([
                    TextInput::make('rconpassword')
                        ->password()
                        ->nullable()
                        ->maxLength(255)
                        ->helperText(function ($component) {
                            $record = $component->getContainer()->getLivewire()->record ?? null;
                            return ($record && !is_null($record->rconpassword))
                                ? 'Is set.'
                                : null;
                        }),
                    Toggle::make('clear_rconpassword')
                        ->default(false)
                        ->visible(function ($component) {
                            $record = $component->getContainer()->getLivewire()->record ?? null;
                            return $record && !is_null($record->rconpassword);
                        })
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->formatStateUsing(fn (string $state): string => UserResource::q3tohtml($state))->html()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ip')
                    ->searchable(),
                Tables\Columns\TextColumn::make('port')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('admin_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('admin_contact')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ping_url')
                    ->searchable(),
                Tables\Columns\IconColumn::make('online')
                    ->boolean(),
                Tables\Columns\IconColumn::make('visible')
                    ->boolean(),
                Tables\Columns\TextColumn::make('map')
                    ->searchable(),
                Tables\Columns\TextColumn::make('defrag')
                    ->searchable(),
                Tables\Columns\TextColumn::make('defrag_gametype')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
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
            'index' => Pages\ListServers::route('/'),
            'create' => Pages\CreateServer::route('/create'),
            'edit' => Pages\EditServer::route('/{record}/edit'),
        ];
    }
}
