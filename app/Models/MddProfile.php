<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

use Illuminate\Support\Str;

class MddProfile extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'id',
        'name',
        'plain_name',
        'country',
        'model',
        'headmodel',
        'cpm_total_records',
        'vq3_total_records',
        'cpm_ctf_records',
        'vq3_ctf_records',
        'cpm_average_rank',
        'vq3_average_rank',
        'cpm_world_records',
        'vq3_world_records',
        'cpm_strafe_records',
        'cpm_rocket_records',
        'cpm_grenade_records',
        'cpm_plasma_records',
        'cpm_bfg_records',
        'vq3_strafe_records',
        'vq3_rocket_records',
        'vq3_grenade_records',
        'vq3_plasma_records',
        'vq3_bfg_records',
    ];

    public function records() {
        return $this->hasMany(Record::class, 'mdd_id', 'id');
    }

    public function processStats() {
        $records = Record::where('mdd_id', $this->id)->with('map')->get();

        $user = User::where('mdd_id', $this->id)->first();

        $cpm_total_records = 0;
        $vq3_total_records = 0;

        $cpm_ctf_records = 0;
        $vq3_ctf_records = 0;

        $cpm_average_rank = 0;
        $vq3_average_rank = 0;

        $cpm_world_records = 0;
        $vq3_world_records = 0;

        $cpm_strafe_records = 0;
        $cpm_rocket_records = 0;
        $cpm_grenade_records = 0;
        $cpm_plasma_records = 0;
        $cpm_bfg_records = 0;

        $vq3_strafe_records = 0;
        $vq3_rocket_records = 0;
        $vq3_grenade_records = 0;
        $vq3_plasma_records = 0;
        $vq3_bfg_records = 0;

        foreach($records as $record) {
            if (Str::contains($record->physics, 'cpm')) {
                if (Str::contains($record->gametype, 'ctf')) {
                    $cpm_ctf_records += 1;
                }
                
                $cpm_total_records += 1;

                $cpm_average_rank += $record->rank;


                if ($record->rank == 1) {
                    $cpm_world_records += 1;
                }

                if ($record->map && $record->map->hasWeapon('rl')) {
                    $cpm_rocket_records += 1;
                }

                if ($record->map && $record->map->hasWeapon('gl')) {
                    $cpm_grenade_records += 1;
                }

                if ($record->map && $record->map->hasWeapon('pg')) {
                    $cpm_plasma_records += 1;
                }

                if ($record->map && $record->map->hasWeapon('bfg')) {
                    $cpm_bfg_records += 1;
                }

                if ($record->map && $record->map->isStrafe()) {
                    $cpm_strafe_records += 1;
                }
            } else {
                if (Str::contains($record->gametype, 'ctf')) {
                    $vq3_ctf_records += 1;
                }
                
                $vq3_total_records += 1;

                $vq3_average_rank += $record->rank;


                if ($record->rank == 1) {
                    $vq3_world_records += 1;
                }

                if ($record->map && $record->map->hasWeapon('rl')) {
                    $vq3_rocket_records += 1;
                }

                if ($record->map && $record->map->hasWeapon('gl')) {
                    $vq3_grenade_records += 1;
                }

                if ($record->map && $record->map->hasWeapon('pg')) {
                    $vq3_plasma_records += 1;
                }

                if ($record->map && $record->map->hasWeapon('bfg')) {
                    $vq3_bfg_records += 1;
                }

                if ($record->map && $record->map->isStrafe()) {
                    $vq3_strafe_records += 1;
                }
            }
        }

        $this->cpm_total_records = $cpm_total_records;
        $this->vq3_total_records = $vq3_total_records;
        
        $this->cpm_ctf_records = $cpm_ctf_records;
        $this->vq3_ctf_records = $vq3_ctf_records;

        if ($cpm_total_records == 0) {
            $this->cpm_average_rank = 0;
        } else {
            $this->cpm_average_rank = $cpm_average_rank / $cpm_total_records;
        }

        if ($vq3_total_records == 0) {
            $this->vq3_average_rank = 0;
        } else {
            $this->vq3_average_rank = $vq3_average_rank / $vq3_total_records;
        }

        $this->cpm_world_records = $cpm_world_records;
        $this->vq3_world_records = $vq3_world_records;

        $this->cpm_strafe_records = $cpm_strafe_records;
        $this->cpm_rocket_records = $cpm_rocket_records;

        $this->cpm_grenade_records = $cpm_grenade_records;
        $this->cpm_plasma_records = $cpm_plasma_records;

        $this->cpm_bfg_records = $cpm_bfg_records;
        $this->vq3_strafe_records = $vq3_strafe_records;

        $this->vq3_rocket_records = $vq3_rocket_records;
        $this->vq3_grenade_records = $vq3_grenade_records;

        $this->vq3_plasma_records = $vq3_plasma_records;
        $this->vq3_bfg_records = $vq3_bfg_records;

        if ($user) {
            $this->user_id = $user->id;
        }

        $this->save();
    }


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
            'plain_name' => $this->generateSubstrings($this->plain_name),
            'created_at' => $this->created_at->timestamp,
        ];
    }
}
