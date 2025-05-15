<?php

namespace App\Filament\Resources\RoundMapResource\Pages;

use App\Filament\Resources\RoundMapResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoundMaps extends ListRecords
{
    protected static string $resource = RoundMapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getMaxContentWidth(): string | null
    {
        return 'full';
    }
}
