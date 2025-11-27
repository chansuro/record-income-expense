<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wfh extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'space',
        'space_occupied',
        'space_unit',
        'time_spend',
        'time_spend_unit',
        'expense_on',
        'elecricity_bill',
        'internet_bill',
        'other_bill',
        'heating_bill',
        'council_tax_bill',
        'rent_or_mortgage',
        'phone_bill',
        'services_bill'    
    
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
