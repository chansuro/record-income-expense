<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    protected function getDisplayMillageDateAttribute($value)
    {
        return \Carbon\Carbon::parse($value)->format('d-m-Y');
    }
}
