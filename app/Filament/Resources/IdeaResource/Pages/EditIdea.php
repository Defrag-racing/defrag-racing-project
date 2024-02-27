<?php

namespace App\Filament\Resources\IdeaResource\Pages;

use App\Filament\Resources\IdeaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIdea extends EditRecord
{
    protected static string $resource = IdeaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
