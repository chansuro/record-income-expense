<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class HmrcQuarterlySubmission extends Model
{
    use HasUuids;

    protected $guarded = [];
    protected $hidden = ['payload', 'user_id', 'authorisation_id'];
    protected $casts = ['payload' => 'encrypted:array', 'submitted_at' => 'datetime'];
}
