<?php

namespace App\Http\Controllers;
use App\Models\Transaction;
use App\Models\Millage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class TaxCalculationController extends Controller
{
    //
    public $tax = [20=>[12571,50270],40=>[50270,125140],45=>[125140]];
    public $taxWeekly = [20=>[12571,50270],40=>[50270,125140],45=>[125140]];
    public $nationalInsurance = 6;
    public function taxyeartodate($calculation_type, $year, $user_id){
        $financialYear = $this->getfyear($year);
        $startYear = $financialYear['start_year'];
        $endYear   = $financialYear['end_year'];
        $startDate = Carbon::createFromDate($startYear, 4, 6); // April 6th of the current year
        $yearArr = explode('-', $year);
        if(count($yearArr) > 1){
            $diffindays = Carbon::createFromDate($yearArr[1], 4, 5)->diffInDays(Carbon::createFromDate($yearArr[0], 4, 6))+1;
            $daysinyear = Carbon::createFromDate($yearArr[0], 4, 6)->diffInDays(Carbon::createFromDate($yearArr[1], 4, 5))+1;
            $endDate = Carbon::createFromDate($endYear, 4, 5); // April 5th of the next year
        }else{
            $endDate = Carbon::today(); // April 5th of the next year
            $diffindays = $endDate->diffInDays(Carbon::createFromDate($yearArr[0], 4, 6))+1;
            $daysinyear = Carbon::createFromDate($yearArr[0], 4, 6)->diffInDays($endDate)+1;
        }
        $weeks = [];
        $weeks[] = [
            'week_start' => ($startDate->toDateString() < "$startYear-04-06") ? "$startYear-04-06 00:00:00": $startDate->toDateString()." 00:00:00",
            'week_end' => $startDate->endOfWeek(Carbon::SUNDAY)->toDateString()." 23:59:59",
        ];
        $startDate = $startDate->endOfWeek(Carbon::SUNDAY)->copy()->addDay(); // Monday
        while($startDate <= $endDate) {
            
            $weekStart = $startDate->copy();
            $weekEnd = $startDate->copy()->endOfWeek(Carbon::SUNDAY); // Get the end of the week
            // Push the start and end date of the week into the weeks array
            if($endDate > $weekEnd){
               $weeks[] = [
                'week_start' => $weekStart->toDateString().' 00:00:00',
                'week_end' => $weekEnd->toDateString().' 23:59:59'
                ]; 
            }
            // Move to the next week
            $startDate->addWeek();
        }
        //$nextyear = $year+1;
        if($weeks[count($weeks)-1]['week_end'] <= "$endYear-04-05 23:59:59"){
            $weeks[] = [
                'week_start' =>Carbon::parse($weeks[count($weeks)-1]['week_end'])->addDay()->toDateString().' 00:00:00',
                'week_end' => "$endYear-04-05 23:59:59"
            ];
        }

        $totalNumberofWeeks = count($weeks);

        if($totalNumberofWeeks > 0){
            $profitArr = $this->getProfit(["$startYear-04-06 00:00:00",$weeks[$totalNumberofWeeks-1]['week_end']],$user_id,$startYear,$calculation_type);
            $profit = $profitArr['income'] - $profitArr['expenses'];
            $personalallowance = config('services.tax.yearly_personal_allowance')/$daysinyear*$diffindays;
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
        //$yearly['start_date'] = new \DateTime($weeks[0]['week_start'])->format('d-m-Y');
        //$yearly['end_date'] = new \DateTime($weeks[$totalNumberofWeeks-1]['week_end'])->format('d-m-Y');
        $yearly['take_home'] = number_format($profit-($nation_insurance+$taxfortheperiod),2,'.','');
        $yearly['year_start'] = Carbon::create($startYear, 04, 06, 0, 0, 0)->format('d-m-Y');
        $yearly['year_end'] = Carbon::create($startYear+1, 04, 05, 0, 0, 0)->format('d-m-Y');
        $yearly['tax_payment_year'] = Carbon::create($startYear+2, 04, 05, 0, 0, 0)->format('Y');
        if($calculation_type == 'millage')
            {
                $yearly['totalmillage'] = $profitArr['totalmillage'];
                $yearly['weekly_millage'] = $profitArr['weekly_millage'];
            }

        return ['response'=>true, 'data'=>$yearly];
    }
     public function taxweekly($calculation_type,$year,$user_id){
        $financialYear = $this->getfyear($year);
        $startYear = $financialYear['start_year'];
        $endYear   = $financialYear['end_year'];
        
        $startDate = Carbon::createFromDate($startYear, 4, 6); // April 6th of the current year
        $endDate = Carbon::createFromDate($endYear, 4, 5); // April 5th of the next ye
        $weeks = [];
        $weeks[] = [
            'week_start' => ($startDate->toDateString() < "$year-04-06") ? "$year-04-06 00:00:00": $startDate->toDateString()." 00:00:00",
            'week_end' => $startDate->endOfWeek(Carbon::SUNDAY)->toDateString()." 23:59:59",
        ];
        $startDate = $startDate->endOfWeek(Carbon::SUNDAY)->copy()->addDay(); // Monday
        while($startDate <= $endDate) {
            
            $weekStart = $startDate->copy();
            $weekEnd = $startDate->copy()->endOfWeek(Carbon::SUNDAY); // Get the end of the week
            // Push the start and end date of the week into the weeks array
            if($endDate > $weekEnd){
               $weeks[] = [
                'week_start' => $weekStart->toDateString().' 00:00:00',
                'week_end' => $weekEnd->toDateString().' 23:59:59'
                ]; 
            }
            // Move to the next week
            $startDate->addWeek();
        }
        //$nextyear = $year+1;
        if($weeks[count($weeks)-1]['week_end'] <= "$endYear-04-05 23:59:59"){
            $weeks[] = [
                'week_start' =>Carbon::parse($weeks[count($weeks)-1]['week_end'])->addDay()->toDateString().' 00:00:00',
                'week_end' => "$endYear-04-05 23:59:59"
            ];
        }
       
        $i=0;
        while(isset($weeks[$i])){
            $toDate = new \DateTime();
            // if($toDate>=new \DateTime($weeks[$i]['week_start']) && $toDate<=new \DateTime($weeks[$i]['week_end'])){
            //     $taxableProfit = 0;
            //     $profit = 0;
            //     $taxableProfittodisplay = 0;
            // }else{
                $startOfWeek = $weeks[$i]['week_start'];
                ($endDate < $weeks[$i]['week_end']) ? $endOfWeek = $endDate : $endOfWeek = $weeks[$i]['week_end'];
                $profitArr = $this->getProfit([$startOfWeek,$endOfWeek],$user_id,$startYear,$calculation_type);
                $profit = $profitArr['income'] - $profitArr['expenses'];
                $personalallowance = config('services.tax.weekly_personal_allowance');
                $personalallowanceYearly = config('services.tax.yearly_personal_allowance');
                $taxslab = $this->tax;
                $taxableProfittodisplay = $profit - $personalallowance;
                ($taxableProfittodisplay > 0)? $taxableProfittodisplay=$taxableProfittodisplay : $taxableProfittodisplay=0;
                $taxableProfit = $profit;
                $taxfortheperiod = 0;
                $taxableProfitforcalculation = $taxableProfit*52;
            //}
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
            if($calculation_type == 'millage')
            {
                $weeks[$i]['totalmillage'] = $profitArr['totalmillage'];
                $weeks[$i]['weekly_millage'] = $profitArr['weekly_millage'];
            }
            $weeks[$i]['take_home'] = number_format($profit-$weeks[$i]['totaltax'],2,'.','');
            $i++;
        }
        return ['response'=>true, 'weeks'=>$weeks];
    }

    public function taxweekly11($year,$user_id){
        $startDate = Carbon::createFromDate($year, 4, 6); // April 6th of the current year
        $endDate = Carbon::createFromDate($year + 1, 4, 5); // April 5th of the next year
        // Ensure that the start date is the Monday of that week
        // if ($startDate->dayOfWeek !== Carbon::MONDAY) {
        //     $startDate = $startDate->next(Carbon::MONDAY);
        // }
        // echo $startDate->toDateString();
        // echo $startDate->endOfWeek()->toDateString();
        // die;
        // Calculate the previous week from April 6th
        //$previousWeekStart = $startDate->copy()->subWeek()->startOfWeek(); // Get the start of the previous week
        //echo $previousWeekStart->toDateString();
        //$previousWeekEnd = $startDate->copy()->subWeek()->endOfWeek(); // Get the end of the previous week
        // Initialize an array to store weeks
        // $weeks = [];
        // $weeks[] = [
        //     'week_start' => ($previousWeekStart->toDateString() < "$year-04-06") ? "$year-04-06 00:00:00": $previousWeekStart->toDateString(),
        //     'week_end' => $previousWeekEnd->toDateString().' 23:59:59',
        // ];
        $weeks = [];
        $weeks[] = [
            'week_start' => ($startDate->toDateString() < "$year-04-06") ? "$year-04-06 00:00:00": $startDate->toDateString(),
            'week_end' => $startDate->endOfWeek().' 23:59:59',
        ];
        // Loop through each week from start date to end date
        // while ($startDate <= $endDate) {
        //     $weekStart = $startDate->copy();
        //     $weekEnd = $startDate->copy()->endOfWeek(); // Get the end of the week

        //     // Push the start and end date of the week into the weeks array
        //     $weeks[] = [
        //         'week_start' => $weekStart->toDateString().' 00:00:00',
        //         'week_end' => $weekEnd->toDateString().' 23:59:59',
        //     ];

        //     // Move to the next week
        //     $startDate->addWeek();
        // }
        //$startDate->addWeek();
        while($startDate <= $endDate) {
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
        print '<pre>';
        print_r($weeks);
        print '</pre>';
        die;
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
        $today = Carbon::today();
        // April 6 of current year
        $fyStart = Carbon::create($year, 4, 6);

        if ($today->lt($fyStart)) {
            // Before April 6 → previous FY
            $startYear = $year - 1;
            $endYear   = $year;
        } else {
            // On/After April 6 → current FY
            $startYear = $year;
            $endYear   = $year + 1;
        }
        $startOfYear = $startYear.'-04-06 00:00:00';
        $endOfYear = $endYear.'-04-05 23:59:59';
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

    public function getProfit($daterange,$user_id,$startYear = null,$type='cash'){
        $startDate = $daterange[0];
        $endDate = $daterange[1];
        
        $totalIncome = Transaction::where('user_id', $user_id)->where('status','1')->where('type','income')->whereBetween('transaction_date',[$startDate, $endDate])->sum('amount');
        if($type == 'cash'){
            $totalExpenses = Transaction::where('user_id', $user_id)->where('status','1')->where('type','expenses')->whereBetween('transaction_date',[$startDate, $endDate])->sum('amount');
            return ['income'=>$totalIncome,'expenses'=>$totalExpenses];
        }elseif($type == 'millage'){

            $totalWeeklyMillage = Millage::where('user_id', $user_id)->whereBetween('millage_date',[$startDate, $endDate])->sum('business_millage');

            $totalMillage = Millage::where('user_id', $user_id)->whereBetween('millage_date',[$startYear.'-04-06', $endDate])->sum('business_millage');
            
            if($totalMillage <= config('services.tax.mileage_celling')){
                // millage rate within celling (10000)
                $mileageRate = config('services.tax.mileage_rate_within_celling');
                $totalExpenses = $totalWeeklyMillage * $mileageRate;
            }elseif($totalMillage > config('services.tax.mileage_celling') && ($totalMillage - $totalWeeklyMillage) >= config('services.tax.mileage_celling')){
                // millage rate above celling (10000)
                $mileageRate = config('services.tax.mileage_rate_above_celling');
                $totalExpenses = $totalWeeklyMillage * $mileageRate;
            }elseif($totalMillage > config('services.tax.mileage_celling') && ($totalMillage - $totalWeeklyMillage) < config('services.tax.mileage_celling')){
                // millage rate above celling (10000) but within celling for the week
                $millagewithinupperlimit = config('services.tax.mileage_celling') - ($totalMillage - $totalWeeklyMillage);
                $mileageRate = config('services.tax.mileage_rate_within_celling');
                $totalExpenses = $millagewithinupperlimit * $mileageRate;
                $mileageRate = config('services.tax.mileage_rate_above_celling');
                $totalExpenses += ($totalMillage - config('services.tax.mileage_celling')) * $mileageRate;
            }
            $totalCategoryExpenses = Transaction::where('user_id', $user_id)->where('status','1')->where('type','expenses')->whereBetween('transaction_date',[$startDate, $endDate])->whereIn('category_list_id', function($query) {
                $query->select('id')
                    ->from('category_lists')
                    ->where('status', 1)->where('parent', 2);
            })->sum('amount');
            return ['income'=>$totalIncome,'expenses'=>$totalExpenses+$totalCategoryExpenses,'totalmillage'=>$totalMillage,'weekly_millage'=>$totalWeeklyMillage];
        }
        

        
    }

    public function getfyear($year){
        $yearArr = explode('-', $year);
        if(count($yearArr) > 1){
            return ['start_year'=>$yearArr[0],'end_year'=>$yearArr[1]];
        }
        $today = Carbon::today();
        // April 6 of current year
        $fyStart = Carbon::create($year, 4, 6);

        if ($today->lt($fyStart)) {
            // Before April 6 → previous FY
            $startYear = $year - 1;
            $endYear   = $year;
        } else {
            // On/After April 6 → current FY
            $startYear = $year;
            $endYear   = $year + 1;
        }
        return ['start_year'=>$startYear,'end_year'=>$endYear];
    }

    function getLastThreeFinancialYears()
    {
        $today = Carbon::today();

        // Find current FY start year
        if ($today->month < 4) {
            $currentFYStart = $today->year - 1;
        } else {
            $currentFYStart = $today->year;
        }
    
        // Start from previous FY
        $startYear = $currentFYStart - 1;
    
        $financialYears = [];
    
        for ($i = 0; $i < 3; $i++) {
            $yearStart = $startYear - $i;
            $yearEnd = $yearStart + 1;
    
            $financialYears[] = [
                'label' => $yearStart . '-' . $yearEnd,
                'start_date' => Carbon::create($yearStart, 4, 6)->toDateString(),
                'end_date' => Carbon::create($yearEnd, 4, 5)->toDateString(),
            ];
        }
    
        return $financialYears;
    }

    
}
