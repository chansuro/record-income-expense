<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Billing extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory;
    protected $fillable = [
        'email',
        'user_id',
        'subscription_id',
        'invoice_id',
        'amount',
        'invoice_status',
        'invoice_date',
        'subscription_from',
        'subscription_to',
        'invoice_link',
        'product_id',
        'currency',
        'plan_id',
        'customer_id'
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}