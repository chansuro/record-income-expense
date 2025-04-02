<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    //
    use HasFactory;
    protected $fillable = [
        'key',
        'subject',
        'body',
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}

