<?php

namespace App\Services;
use App\Models\Wfh;

class BillPercentageCalculatorService
{
    /**
     * Calculate bill percentage based on space and time spent.
     *
     * @param float $space
     * @param float $time
     * @param string $type
     * @return float
     */
    public function calculate($user_id): float
    {
        $wfh = Wfh::where('user_id',$user_id)->first();
        $space_percentage = ($wfh->space_occupied / $wfh->space) * 100;
        $time_percentage = ($wfh->time_spend / (24 * 7)) * 100; // Assuming time_spend is in hours per week
        $electric_bill_cinsumption = $wfh->elecricity_bill;
    }
}
