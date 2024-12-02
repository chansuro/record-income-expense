<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Millage extends Model
{
    //
    /** @use HasFactory<\Database\Factories\CategoryListFactory> */
    use HasFactory;
    protected $fillable = [
        'business_millage',
        'personal_millage',
        'millage_date',
        'user_id',
        'document',
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
