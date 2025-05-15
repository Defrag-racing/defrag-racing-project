<?php

namespace App\Filament\Resources\RoundResource\Pages;

use App\Filament\Resources\RoundResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRounds extends ListRecords
{
    protected static string $resource = RoundResource::class;

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
