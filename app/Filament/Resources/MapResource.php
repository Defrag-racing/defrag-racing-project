<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MapResource\Pages;
use App\Filament\Resources\MapResource\RelationManagers;
use App\Models\Map;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MapResource extends Resource
{
    protected static ?string $model = Map::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static bool $shouldSkipAuthorization = true;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('description')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('author')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('pk3')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('pk3_size')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('thumbnail')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('physics')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('gametype')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('mod')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('weapons')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('items')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('functions')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Toggle::make('visible')
                    ->required(),
                Forms\Components\DateTimePicker::make('date_added')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable(),
                Tables\Columns\TextColumn::make('author')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pk3')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pk3_size')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('thumbnail')
                    ->searchable(),
                Tables\Columns\TextColumn::make('physics')
                    ->searchable(),
                Tables\Columns\TextColumn::make('gametype')
                    ->searchable(),
                Tables\Columns\TextColumn::make('mod')
                    ->searchable(),
                Tables\Columns\TextColumn::make('weapons')
                    ->searchable(),
                Tables\Columns\TextColumn::make('items')
                    ->searchable(),
                Tables\Columns\TextColumn::make('functions')
                    ->searchable(),
                Tables\Columns\IconColumn::make('visible')
                    ->boolean(),
                Tables\Columns\TextColumn::make('date_added')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
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
            'index' => Pages\ListMaps::route('/'),
            'create' => Pages\CreateMap::route('/create'),
            'edit' => Pages\EditMap::route('/{record}/edit'),
        ];
    }
}
