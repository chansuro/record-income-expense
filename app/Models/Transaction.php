<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory;
    protected $fillable = [
        'title',
        'user_id',
        'amount',
        'category_list_id',
        'type',
        'document',
        'status',
        'paymentmethod',
        'transaction_date',
        'is_recurring',
        'recurring_period',
        'parent_transaction'
    ];
    protected $hidden = [
        'updated_at',
    ];
    protected function getCreatedAtAttribute($value)
    {
        return \Carbon\Carbon::parse($value)->format('d-m-Y');
    }

    protected function getTransactionDateAttribute($value)
    {
        return \Carbon\Carbon::parse($value)->format('d-m-Y');
    }

    public function category_list(): BelongsTo{
        return $this->belongsTo(CategoryList::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
