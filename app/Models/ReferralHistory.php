<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralHistory extends Model
{
    //
    use HasFactory;
    protected $fillable = [
        'referred_id',
        'referrer_id',
        'redeemed',
        'redeemed_date',
        'redemption_details',
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}