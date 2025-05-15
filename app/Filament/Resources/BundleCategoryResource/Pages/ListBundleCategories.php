<?php

namespace App\Filament\Resources\BundleCategoryResource\Pages;

use App\Filament\Resources\BundleCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBundleCategories extends ListRecords
{
    protected static string $resource = BundleCategoryResource::class;

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
