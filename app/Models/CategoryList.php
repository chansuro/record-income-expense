<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryList extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryListFactory> */
    use HasFactory;
    protected $fillable = [
        'title',
        'type',
        'user_id',
        'status',
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

}
