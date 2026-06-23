<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Depreciation extends Model
{
    //
    /** @use HasFactory<\Database\Factories\CategoryListFactory> */
    use HasFactory;
    protected $attributes = [
    'dep_percentage' => 14,
    ];
    protected $fillable = [
        'user_id',
        'vehicle_cost',
        'emission_type',
        'dep_percentage',
        'expense_id'
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
