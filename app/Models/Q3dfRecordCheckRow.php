<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Q3dfRecordCheckRow extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'date_set' => 'datetime',
        'imported_at' => 'datetime',
    ];

    /** The row in the shape Q3DFRecordsApi hands to the importer. */
    public function toApiRecord(): array
    {
        return [
            'name' => $this->name,
            'country' => $this->country,
            'mdd_id' => $this->mdd_id,
            'time' => $this->q3df_time,
            'map' => $this->mapname,
            'physics' => $this->physics,
            'mode' => $this->mode,
            'date' => $this->date_set->toDateTimeString(),
        ];
    }
}
