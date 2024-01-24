<?php

namespace App\Filament\Resources\MapResource\Pages;

use App\Filament\Resources\MapResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMap extends EditRecord
{
    protected static string $resource = MapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
