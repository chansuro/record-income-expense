<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];
    protected $hidden = [
        'updated_at',
    ];
}
