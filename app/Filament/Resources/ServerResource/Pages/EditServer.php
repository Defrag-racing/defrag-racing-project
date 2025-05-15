<?php

namespace App\Filament\Resources\ServerResource\Pages;

use App\Filament\Resources\ServerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServer extends EditRecord
{
    protected static string $resource = ServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if(!filled($data['rconpassword']) && empty($data['clear_rconpassword'])) {
            unset($data['rconpassword']);
        }

        if (!empty($data['clear_rconpassword'])) {
            $data['rconpassword'] = null;
        }

        unset($data['clear_rconpassword']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->form->fill(array_merge(
            $this->form->getState(),
            ['clear_rconpassword' => false]
        ));
    }
}
