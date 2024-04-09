<?php

namespace App\Filament\Resources\RoundMapResource\Pages;

use App\Filament\Resources\RoundMapResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRoundMap extends EditRecord
{
    protected static string $resource = RoundMapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
