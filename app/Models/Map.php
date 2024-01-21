<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Map extends Model
{
    use HasFactory;
    use Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'description',
        'author',
        'pk3',
        'pk3_size',
        'thumbnail',
        'physics',
        'gametype',
        'mod',
        'weapons',
        'items',
        'functions',
        'visible',
        'date_added'
    ];


    public function generateSubstrings($name) {
        $inputString = mb_convert_encoding($name, 'Windows-1252', "auto");
        $length = strlen($inputString);
        $result = [];
    
        for ($i = 0; $i <= $length; $i++) {
            $sub = substr($inputString, $i);

            if (strlen($sub) < 3) {
                break;
            }

            $result[] = $sub;
        }

        if (count($result) == 0) {
            $result[] = $inputString;
        }
    
        return $result;
    }

    public function toSearchableArray () {
        return [
            'id' => (string) $this->id,
            'name' => $this->generateSubstrings($this->name),
            'created_at' => $this->created_at->timestamp,
        ];
    }

    public function records () {
        return $this->hasMany(Record::class, 'mapname', 'name')->orderBy('date_set', 'DESC');
    }
}
