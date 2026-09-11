<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HmrcClientAuthorisation extends Model
{
    protected $fillable = [
        'user_id',
        'environment',
        'service',
        'client_type',
        'client_id_type',
        'client_id',
        'nino',
        'post_code',
        'typeOfBusiness',
        'businessId',
        'tradingType',
        'tradingName',
        'invitation_id',
        'client_action_url',
        'vehicle_exp',
        'agent_type',
        'status',
        'expires_at',
        'accepted_at',
        'last_checked_at',
    ];

    protected $casts = [
        'client_id' => 'encrypted',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}