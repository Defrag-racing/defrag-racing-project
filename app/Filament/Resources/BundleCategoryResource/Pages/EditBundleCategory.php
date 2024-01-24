<?php

namespace App\Filament\Resources\BundleCategoryResource\Pages;

use App\Filament\Resources\BundleCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBundleCategory extends EditRecord
{
    protected static string $resource = BundleCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
