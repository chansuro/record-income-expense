<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    //
    use HasFactory;
    protected $fillable = [
        'is_alerm',
        'user_id',
        'reminder_time',
        'repeat_on',
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
