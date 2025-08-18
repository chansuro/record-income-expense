<?php

namespace App\Http\Controllers;
use App\Models\Transaction;
use Carbon\Carbon;

use Illuminate\Http\Request;

class TaxCalculationController extends Controller
{
    //
    public $tax = [20=>[12571,50270],40=>[50270,125140],45=>[125140]];
    public $taxWeekly = [20=>[12571,50270],40=>[50270,125140],45=>[125140]];
    public $nationalInsurance = 6;
    public function taxyeartodate($year,$user_id){
        $startDate = Carbon::createFromDate($year, 4, 6); // April 6th of the current year
        $endDate = Carbon::today(); // April 5th of the next year
        // Ensure that the start date is the Monday of that week
        if ($startDate->dayOfWeek !== Carbon::MONDAY) {
            $startDate = $startDate->next(Carbon::MONDAY);
        }
        // Calculate the previous week from April 6th
        $previousWeekStart = $startDate->copy()->subWeek()->startOfWeek(); // Get the start of the previous week
        $previousWeekEnd = $startDate->copy()->subWeek()->endOfWeek(); // Get the end of the previous week
        // Initialize an array to store weeks
        $weeks = [];
        $weeks[] = [
            'week_start' => ($previousWeekStart->toDateString() < "$year-04-06") ? "$year-04-06 00:00:00": $previousWeekStart->toDateString(),
            'week_end' => $previousWeekEnd->toDateString().' 23:59:59',
        ];
        while ($startDate <= $endDate) {
            $weekStart = $startDate->copy();
            $weekEnd = $startDate->copy()->endOfWeek(); // Get the end of the week

            // Push the start and end date of the week into the weeks array
            if($endDate > $weekEnd){
               $weeks[] = [
                'week_start' => $weekStart->toDateString().' 00:00:00',
                'week_end' => $weekEnd->toDateString().' 23:59:59',
                ]; 
            }
            // Move to the next week
            $startDate->addWeek();
        }
        $totalNumberofWeeks = count($weeks);
        if($totalNumberofWeeks > 0){
            $profitArr = $this->getProfit(["$year-04-06 00:00:00",$weeks[$totalNumberofWeeks-1]['week_end']],$user_id);
            $profit = $profitArr['income'] - $profitArr['expenses'];
            $personalallowance = config('services.tax.weekly_personal_allowance')*$totalNumberofWeeks;
            $personalallowanceYearly = config('services.tax.yearly_personal_allowance');
            $taxslab = $this->tax;
            $taxableProfit = $profit - $personalallowance;
            ($taxableProfit > 0)? $taxableProfit=$taxableProfit : $taxableProfit=0;
            $taxfortheperiod = 0;
            $taxableProfitforcalculation = ($taxableProfit/$totalNumberofWeeks)*52;
            if($taxableProfitforcalculation > 0)
            {
                if($taxableProfitforcalculation<=$taxslab[20][1]){
                    $taxfortheperiod = $taxableProfitforcalculation*(20/100);
                    $nation_insurance = $taxableProfitforcalculation*($this->nationalInsurance/100);
                    
                }else{
                    $taxfortheperiod = ($taxslab[20][1] - $personalallowanceYearly)*(20/100);
                    $nation_insurance = ($taxslab[20][1] - $personalallowanceYearly)*($this->nationalInsurance/100);
                    $remainingAmt = $taxableProfitforcalculation-$taxslab[20][1];
                    $taxfortheperiod = $taxfortheperiod+($remainingAmt*(40/100));
                    $nation_insurance = $nation_insurance+$remainingAmt*(2/100);
                }
                $taxfortheperiod = ($taxfortheperiod/52)*$totalNumberofWeeks;
                $nation_insurance = ($nation_insurance/52)*$totalNumberofWeeks;
            }else{
                $nation_insurance = 0;
                $taxfortheperiod = 0;
            }
        }else{
            $nation_insurance = 0;
            $taxfortheperiod = 0;
        }
        $yearly['taxfortheperiod'] = number_format($taxfortheperiod,2,'.','');
        $yearly['national_insurance'] = number_format($nation_insurance,2,'.','');
        $yearly['totaltax'] = number_format(($nation_insurance+$taxfortheperiod),2,'.','');
        $yearly['income'] = number_format($profitArr['income'],2,'.','');
        $yearly['expenses'] = number_format($profitArr['expenses'],2,'.','');
        $yearly['profit'] = number_format($profit,2,'.','');
        $yearly['personal_allowance'] = number_format($personalallowance,2,'.','');
        $yearly['taxableprofit'] = number_format(($taxableProfit),2,'.','');
        $yearly['start_date'] = new \DateTime($weeks[0]['week_start'])->format('d-m-Y');
        $yearly['end_date'] = new \DateTime($weeks[$totalNumberofWeeks-1]['week_end'])->format('d-m-Y');
        $yearly['take_home'] = number_format($profit-($nation_insurance+$taxfortheperiod),2,'.','');
        $yearly['year_start'] = Carbon::create($year, 04, 06, 0, 0, 0)->format('d-m-Y');
        $yearly['year_end'] = Carbon::create($year+1, 04, 05, 0, 0, 0)->format('d-m-Y');
        $yearly['tax_payment_year'] = Carbon::create($year+2, 04, 05, 0, 0, 0)->format('Y');
        

        return ['response'=>true, 'data'=>$yearly];
    }
    public function taxweekly($year,$user_id){
        $startDate = Carbon::createFromDate($year, 4, 6); // April 6th of the current year
        $endDate = Carbon::createFromDate($year + 1, 4, 5); // April 5th of the next year
        // Ensure that the start date is the Monday of that week
        if ($startDate->dayOfWeek !== Carbon::MONDAY) {
            $startDate = $startDate->next(Carbon::MONDAY);
        }
        // Calculate the previous week from April 6th
        $previousWeekStart = $startDate->copy()->subWeek()->startOfWeek(); // Get the start of the previous week
        $previousWeekEnd = $startDate->copy()->subWeek()->endOfWeek(); // Get the end of the previous week
        // Initialize an array to store weeks
        $weeks = [];
        $weeks[] = [
            'week_start' => ($previousWeekStart->toDateString() < "$year-04-06") ? "$year-04-06 00:00:00": $previousWeekStart->toDateString(),
            'week_end' => $previousWeekEnd->toDateString().' 23:59:59',
        ];

        // Loop through each week from start date to end date
        while ($startDate <= $endDate) {
            $weekStart = $startDate->copy();
            $weekEnd = $startDate->copy()->endOfWeek(); // Get the end of the week

            // Push the start and end date of the week into the weeks array
            $weeks[] = [
                'week_start' => $weekStart->toDateString().' 00:00:00',
                'week_end' => $weekEnd->toDateString().' 23:59:59',
            ];

            // Move to the next week
            $startDate->addWeek();
        }
        $i=0;
        while(isset($weeks[$i])){
            $toDate = new \DateTime();
            if($toDate>=new \DateTime($weeks[$i]['week_start']) && $toDate<=new \DateTime($weeks[$i]['week_end'])){
                $taxableProfit = 0;
                $profit = 0;
                $taxableProfittodisplay = 0;
            }else{
                $startOfWeek = $weeks[$i]['week_start'];
                ($endDate < $weeks[$i]['week_end']) ? $endOfWeek = $endDate : $endOfWeek = $weeks[$i]['week_end'];
                $profitArr = $this->getProfit([$startOfWeek,$endOfWeek],$user_id);
                $profit = $profitArr['income'] - $profitArr['expenses'];
                $personalallowance = config('services.tax.weekly_personal_allowance');
                $personalallowanceYearly = config('services.tax.yearly_personal_allowance');
                $taxslab = $this->tax;
                $taxableProfittodisplay = $profit - $personalallowance;
                ($taxableProfittodisplay > 0)? $taxableProfittodisplay=$taxableProfittodisplay : $taxableProfittodisplay=0;
                $taxableProfit = $profit;
                $taxfortheperiod = 0;
                $taxableProfitforcalculation = $taxableProfit*52;
             }
            if($taxableProfit > 0)
            {
                if($taxableProfitforcalculation<=$taxslab[20][0]){
                    $nation_insurance = 0;
                    $taxfortheperiod = 0;
                }
                elseif($taxableProfitforcalculation<=$taxslab[20][1] && $taxableProfitforcalculation>=$taxslab[20][0]){
                    $taxfortheperiod = ($taxableProfitforcalculation - $personalallowanceYearly)*(20/100);
                    $nation_insurance = ($taxableProfitforcalculation - $personalallowanceYearly)*($this->nationalInsurance/100);
                }else{
                    $taxfortheperiod = ($taxslab[20][1] - $personalallowanceYearly)*(20/100);
                    $nation_insurance = ($taxslab[20][1] - $personalallowanceYearly)*($this->nationalInsurance/100);
                    $remainingAmt = $taxableProfitforcalculation-$taxslab[20][1];
                    $taxfortheperiod = $taxfortheperiod+($remainingAmt*(40/100));
                    $nation_insurance = $nation_insurance+$remainingAmt*(2/100);
                }
                $taxfortheperiod = $taxfortheperiod/52;
                $nation_insurance = $nation_insurance/52;
            }else{
                $nation_insurance = 0;
                $taxfortheperiod = 0;
                $taxableProfit = 0;
            }
            $weeks[$i]['week_number'] = $i+1;
            $weeks[$i]['taxfortheperiod'] = number_format($taxfortheperiod,2,'.','');
            $weeks[$i]['national_insurance'] = number_format($nation_insurance,2,'.','');
            $weeks[$i]['totaltax'] = number_format(($nation_insurance+$taxfortheperiod),2,'.','');
            $weeks[$i]['income'] = number_format($profitArr['income'],2,'.','');
            $weeks[$i]['expenses'] = number_format($profitArr['expenses'],2,'.','');
            $weeks[$i]['profit'] = number_format($profit,2,'.','');
            $weeks[$i]['personal_allowance'] = config('services.tax.weekly_personal_allowance');
            $weeks[$i]['taxable_profit'] = number_format(($taxableProfittodisplay),2,'.','');
            $weeks[$i]['national_insurance'] = number_format($nation_insurance,2,'.','');
            $weeks[$i]['week_start'] = date("d-m-Y",strtotime($weeks[$i]['week_start']));
            $weeks[$i]['week_end'] = date("d-m-Y",strtotime($weeks[$i]['week_end']));

            $weeks[$i]['take_home'] = number_format($profit-$weeks[$i]['totaltax'],2,'.','');
            $i++;
        }

        // $Weekdays = $this->getStartAndEndOfWeek($year,$weeknumber);
        // $startOfWeek = $Weekdays['start'].' 00:00:00';
        // $endOfWeek = $Weekdays['end'].' 23:59:59';
        // $profit = $this->getProfit([$startOfWeek,$endOfWeek],$user_id);
        // $personalallowance = env("YEARLY_PERSONAL_ALLOWANCE");
        // $taxslab = $this->tax;
        // $taxableProfit = $profit - $personalallowance;
        // $taxfortheperiod = 0;
        // if($taxableProfit > 0)
        // {
        //     if($taxableProfit<=$taxslab[20][1]){
        //         $taxfortheperiod = ($taxableProfit - $personalallowance)*(20/100);
        //         $nation_insurance = ($taxableProfit - $personalallowance)*($this->nationalInsurance/100);
        //     }else{
        //         $taxfortheperiod = ($taxslab[20][1] - $personalallowance)*(20/100);
        //         $nation_insurance = ($taxslab[20][1] - $personalallowance)*($this->nationalInsurance/100);
        //         $remainingAmt = $taxableProfit-$taxslab[20][1];
        //         $taxfortheperiod = $taxfortheperiod+($remainingAmt*(40/100));
        //         $nation_insurance = $nation_insurance+$remainingAmt*(2/100);
        //         // if(($remainingAmt)<=$taxslab[40][1]){
        //         //     $taxfortheperiod = $taxfortheperiod+($remainingAmt*(40/100));
        //         // }else{
        //         //     $taxfortheperiod = $taxfortheperiod+(($taxslab[40][1]/52)*(40/100));
        //         //     $endremainingamount = $remainingAmt-($taxslab[40][1]/52);
        //         //     if($endremainingamount > 0){
        //         //         $taxfortheperiod = $taxfortheperiod+($endremainingamount*(45/100));
        //         //     }
        //         // }
        //     }
        //     $taxfortheperiod = $taxfortheperiod/52;
        //     $nation_insurance = $nation_insurance/52;
        // }else{
        //     $nation_insurance = 0;
        //     $taxfortheperiod = 0;
        // }
        //return ['response'=>true, 'data'=>['taxfortheperiod'=>$taxfortheperiod,'national_insurance'=>$nation_insurance,'totaltax'=>($nation_insurance+$taxfortheperiod),'profit'=>$profit]];
        return ['response'=>true, 'weeks'=>$weeks];
    }

    public function taxyearly($year,$user_id){
        // $currentDate = new \DateTime();
        // $startOfYear = $currentDate->format('Y').'-04-06 00:00:00';
        // $endOfYear = $currentDate->format('Y').'-04-05 23:59:59';
        $startOfYear = $year.'-04-06 00:00:00';
        $endOfYear = ($year+1).'-04-05 23:59:59';
        $profitArr = $this->getProfit([$startOfYear,$endOfYear],$user_id);
        $profit = $profitArr['income'] - $profitArr['expenses'];
        $personalallowance = config('services.tax.yearly_personal_allowance');
        $taxslab = $this->tax;
        $taxableProfit = $profit - $personalallowance;
        $taxfortheperiod = 0;
        if($taxableProfit > 0)
        {
            if($taxableProfit<=$taxslab[20][0]){
                $nation_insurance = 0;
                $taxfortheperiod = 0;
            }
            elseif($taxableProfit<=$taxslab[20][1] && $taxableProfit>=$taxslab[20][0]){
                $taxfortheperiod = ($taxableProfit - $personalallowance)*(20/100);
                $nation_insurance = ($taxableProfit - $personalallowance)*($this->nationalInsurance/100);
            }else{
                $taxfortheperiod = ($taxslab[20][1] - $personalallowance)*(20/100);
                $nation_insurance = ($taxslab[20][1] - $personalallowance)*($this->nationalInsurance/100);
                $remainingAmt = $taxableProfit-$taxslab[20][1];
                $taxfortheperiod = $taxfortheperiod+($remainingAmt*(40/100));
                $nation_insurance = $nation_insurance+$remainingAmt*(2/100);
                // if(($remainingAmt)<=$taxslab[40][1]){
                //     $taxfortheperiod = $taxfortheperiod+($remainingAmt*(40/100));
                // }else{
                //     $taxfortheperiod = $taxfortheperiod+(($taxslab[40][1]/52)*(40/100));
                //     $endremainingamount = $remainingAmt-($taxslab[40][1]/52);
                //     if($endremainingamount > 0){
                //         $taxfortheperiod = $taxfortheperiod+($endremainingamount*(45/100));
                //     }
                // }
            }
            $taxfortheperiod = $taxfortheperiod;
            $nation_insurance = $nation_insurance;
        }else{
            $nation_insurance = 0;
            $taxfortheperiod = 0;
        }
        return ['response'=>true, 'data'=>['taxfortheperiod'=>number_format($taxfortheperiod,2,'.',''),'national_insurance'=>number_format($nation_insurance,2,'.',''),'totaltax'=>number_format(($nation_insurance+$taxfortheperiod),2,'.',''),'taxableprofit'=>number_format($taxableProfit,2,'.',''),'profit'=>number_format($profit,2,'.',''),'personal_allowance' => config('services.tax.yearly_personal_allowance'),'take_home'=>number_format($profit-($nation_insurance+$taxfortheperiod),2,'.','')]];
    }

    function getStartAndEndOfWeek($year, $weekNumber) {
        // Get the start date of the given week number
        $startDate = new DateTime();
        $startDate->setISODate($year, $weekNumber, 1); // '1' is Monday (the start of the week)
        
        // Get the end date (Sunday) of the given week number
        $endDate = clone $startDate;
        $endDate->modify('+6 days'); // Move to Sunday
        
        // Return both dates
        return [
            'start' => $startDate->format('Y-m-d'),
            'end' => $endDate->format('Y-m-d'),
        ];
    }

    public function getProfit($daterange,$user_id){
        $startDate = $daterange[0];
        $endDate = $daterange[1];
        $totalIncome = Transaction::where('user_id', $user_id)->where('status','1')->where('type','income')->whereBetween('transaction_date',[$startDate, $endDate])->sum('amount');
        $totalExpenses = Transaction::where('user_id', $user_id)->where('status','1')->where('type','expenses')->whereBetween('transaction_date',[$startDate, $endDate])->sum('amount');
        return ['income'=>$totalIncome,'expenses'=>$totalExpenses];
    }
}
