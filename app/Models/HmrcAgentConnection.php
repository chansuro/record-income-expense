<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HmrcAgentConnection extends Model
{
    protected $fillable = [
        'arn',
        'environment',
        'access_token',
        'refresh_token',
        'expires_at',
        'scope',
        'connected_by',
        'connected_at',
        'last_refreshed_at',
        'is_active',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
        'connected_at' => 'datetime',
        'last_refreshed_at' => 'datetime',
        'is_active' => 'boolean',
    ];
}