<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'text',
        'type'
    ];

    protected static function booted(): void {
        static::created(function (Announcement $announcement) {
            User::all()->each->systemNotifyAnnouncement('announcement', 'A new Announcement has been created', $announcement->title, '', '/announcements');
        });
    }
}
