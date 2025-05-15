<?php

namespace App\Filament\Pages;

use Illuminate\Support\Collection;
use Mokhosh\FilamentKanban\Pages\KanbanBoard;

use App\Models\Idea;
use App\Models\IdeaStatus;
use Filament\Actions\CreateAction;
use Filament\Forms;

class IdeasBoard extends KanbanBoard
{
    protected static string $model = Idea::class;
    protected static string $statusEnum = IdeaStatus::class;
    

    protected function getHeaderActions() : array {
        return [
            CreateAction::make()
                ->model(Idea::class)
                ->form([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Textarea::make('description'),
                    Forms\Components\Select::make('status')
                        ->options([
                            'Future'        => 'Future',
                            'Current'       => 'Current',
                            'ToDo'          => 'ToDo',
                            'Completed'     => 'Completed',
                        ])
                ])
            ];
    }

    public function getMaxContentWidth(): string | null
    {
        return 'full';
    }
}
