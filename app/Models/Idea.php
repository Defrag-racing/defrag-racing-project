<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Mokhosh\FilamentKanban\Concerns\IsKanbanStatus;
 
enum IdeaStatus: string {
    use IsKanbanStatus;
 
    case Future = 'Future';
    case Current = 'Current';
    case ToDo = 'ToDo';
    case Completed = 'Completed';
}

class Idea extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'status'];
}
