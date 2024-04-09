<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoundMapResource\Pages;
use App\Filament\Resources\RoundMapResource\RelationManagers;
use App\Models\RoundMap;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RoundMapResource extends Resource
{
    protected static ?string $model = RoundMap::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('round_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('download_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('pk3')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('crc')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Toggle::make('external')
                    ->required()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('round_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('download_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pk3')
                    ->searchable(),
                Tables\Columns\TextColumn::make('crc')
                    ->searchable(),
                Tables\Columns\IconColumn::make('external')
                    ->boolean()
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
            'index' => Pages\ListRoundMaps::route('/'),
            'create' => Pages\CreateRoundMap::route('/create'),
            'edit' => Pages\EditRoundMap::route('/{record}/edit'),
        ];
    }
}
