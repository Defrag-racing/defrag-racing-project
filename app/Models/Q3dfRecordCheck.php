<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Q3dfRecordCheck extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(Q3dfRecordCheckRow::class, 'check_id');
    }

    public function isBusy(): bool
    {
        return in_array($this->status, ['queued', 'running', 'importing'], true);
    }
}
