<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\Transaction;

class DashboardController extends Controller
{
    //
    public function index($user_id){
        $today = Carbon::now();
        $dateSearchCurrent = $today->format('Y').'-'.$today->format('m').'-'.$today->format('d').' 23:59:59';
        $firstdayofmonth = Carbon::parse($today->format('Y').'-'.$today->format('m').'-1')->format('d-M-Y');
        $lastdayofmonth = Carbon::parse($today->format('Y').'-'.$today->format('m').'-'.$today->format('d'))->format('d-M-Y');
        $newDate = $today->subMonth(11);
        $dateSearchLast = $newDate->format('Y').'-'.$newDate->format('m').'-01 00:00:00';
        $newArr = [];
        $results = DB::select('SELECT sum(`amount`) as total,DATE(transaction_date) AS date_part,type FROM transactions WHERE transaction_date between ? and ? and user_id=? group by date_part, type', [$dateSearchLast,$dateSearchCurrent,$user_id]);
        $resultsMillage = DB::select('SELECT sum(`business_millage`) as total_business_millage, sum(`personal_millage`) as total_personal_millage,millage_date from millages where millage_date  between ? and ? and user_id=? group by millage_date', [$dateSearchLast,$dateSearchCurrent,$user_id]);
        for($i=0;$i<count($results);$i++){
            $resultsArray = get_object_vars($results[$i]);
            $newArr[$resultsArray['date_part']][$resultsArray['type']] = $resultsArray;
        }
        for($i=0;$i<count($resultsMillage);$i++){
            $resultsArray = get_object_vars($resultsMillage[$i]);
            $newArr[$resultsArray['millage_date']]['millage'] = $resultsArray;
        }
        $currentmonthincome = 0;
        $currentmonthexpenditure = 0;

        $currentmonthbusiness_millage = 0;
        $currentmonthpersonal_millage = 0;

        $today = Carbon::now();
        $maxMonthlyProfit = 0;
        $currentmonthgraphdata = [];
        $months = [];
        for($i=0;$i<12;$i++){
            $month = $today->format('m');
            $year = $today->format('Y');
            $date = $today->format('d');
            $daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year)); 
            $days = [];
            $monthlyProfilt = 0;
            if($i == 0){
                for($j=$date;$j>=1;$j--){
                    $profiltoftheday = 0;
                    $date = $year.'-'.$month.'-'.str_pad($j, 2, "0", STR_PAD_LEFT);
                    $dateObj = Carbon::parse($date);
                    $dateSearch = $dateObj->format('Y-m-d');
                    if(isset($newArr[$date]))
                    {
                        if(isset($newArr[$date]['income'])){
                            $incomeDatewise = $newArr[$date]['income']['total'];
                        }else{
                            $incomeDatewise = 0;
                        }

                        if(isset($newArr[$date]['expenses'])){
                            $expenditureDatewise = $newArr[$date]['expenses']['total'];
                        }else{
                            $expenditureDatewise = 0;
                        }
                        if(isset($newArr[$date]['millage'])){
                            $business_millage = $newArr[$date]['millage']['total_business_millage'];
                            $personal_millage = $newArr[$date]['millage']['total_personal_millage'];
                        }else{
                            $business_millage = 0;
                            $personal_millage = 0;
                        }
                    }else{
                        $incomeDatewise = 0;
                        $expenditureDatewise = 0;
                        $business_millage = 0;
                        $personal_millage = 0;
                    }
                    $profiltoftheday = $incomeDatewise-$expenditureDatewise;
                    $monthlyProfilt = $monthlyProfilt + $profiltoftheday;
                    // only for current month
                    $currentmonthincome = $currentmonthincome+$incomeDatewise;
                    $currentmonthexpenditure = $currentmonthexpenditure+$expenditureDatewise;
                    $currentmonthbusiness_millage = $currentmonthbusiness_millage+$business_millage;
                    $currentmonthpersonal_millage = $currentmonthpersonal_millage+$personal_millage;

                    $days[] = array('displayDate'=>$dateObj->format('D j'),'date'=>$dateSearch,'income'=>number_format($incomeDatewise,2),'enpense'=>number_format($expenditureDatewise,2),'business_millage'=>$business_millage,'personal_millage'=>$personal_millage,'profit'=>number_format($profiltoftheday,2));
                    $currentmonthgraphdata = $days;
                }
            }
            else{
                for($j=0;$j<$daysInMonth;$j++){
                    $profiltoftheday = 0;
                    $date = $year.'-'.$month.'-'.str_pad(($j+1), 2, "0", STR_PAD_LEFT);
                    $dateObj = Carbon::parse($date);
                    $dateSearch = $dateObj->format('Y-m-d');
                    
                    if(isset($newArr[$date]))
                    {
                        if(isset($newArr[$date]['income'])){
                            $incomeDatewise = $newArr[$date]['income']['total'];
                        }else{
                            $incomeDatewise = 0;
                        }

                        if(isset($newArr[$date]['expenses'])){
                            $expenditureDatewise = $newArr[$date]['expenses']['total'];
                        }else{
                            $expenditureDatewise = 0;
                        }
                        if(isset($newArr[$date]['millage'])){
                            $business_millage = $newArr[$date]['millage']['total_business_millage'];
                            $personal_millage = $newArr[$date]['millage']['total_personal_millage'];
                        }else{
                            $business_millage = 0;
                            $personal_millage = 0;
                        }
                    }else{
                        $incomeDatewise = 0;
                        $expenditureDatewise = 0;
                        $business_millage = 0;
                        $personal_millage = 0;
                    }
                    $profiltoftheday = $incomeDatewise-$expenditureDatewise;
                    $monthlyProfilt = $monthlyProfilt + $profiltoftheday;
                    $days[] = array('displayDate'=>$dateObj->format('D j'),'date'=>$dateSearch,'income'=>number_format($incomeDatewise,2),'enpense'=>number_format($expenditureDatewise,2),'business_millage'=>$business_millage,'personal_millage'=>$personal_millage,'profit'=>number_format($profiltoftheday,2,".",""));
                }
            }
            if($monthlyProfilt>$maxMonthlyProfit){
                $maxMonthlyProfit = $monthlyProfilt;
            }
            $months[$i] = array('displayMonth'=>$today->format('M'),'month'=>$today->format('m'),'year'=>$today->format('Y'),'days'=>$days,'monthlyprofit'=>number_format($monthlyProfilt,2,'.',''));
            $today->startOfMonth()->subMonth();
        }

        $currentDate = new \DateTime();
        $currentDate->modify('this week');
        $startOfWeek = $currentDate->format('Y-m-d').' 00:00:00';

        $startOfWeekY = $currentDate->format('Y');
        $startOfWeekM = $currentDate->format('m');
        $startOfWeekD = $currentDate->format('d');
        $currentDate->modify('next Sunday');
        $endOfWeek = $currentDate->format('Y-m-d').' 23:59:59';
        $date = Carbon::now();
        $date->setDate($startOfWeekY, $startOfWeekM, $startOfWeekD);
        $weeklyresults = DB::select('SELECT sum(`amount`) as total,DATE(transaction_date) AS date_part,type FROM transactions WHERE transaction_date between ? and ? and user_id=? group by date_part, type', [$startOfWeek,$endOfWeek,$user_id]);
        $newWeeklyArr = [];
        for($i=0;$i<count($weeklyresults);$i++){
            $weeklyresultsArray = get_object_vars($weeklyresults[$i]);
            $newWeeklyArr[$weeklyresultsArray['date_part']][$weeklyresultsArray['type']] = $weeklyresultsArray;
        }
        $maxdailyProfilt = 0;
        for($k=0;$k<7;$k++){
            $dateSearch = $date->format('Y-m-d');
            if(isset($newWeeklyArr[$dateSearch]))
            {
                if(isset($newWeeklyArr[$dateSearch]['income'])){
                    $incomeDatewise = $newWeeklyArr[$dateSearch]['income']['total'];
                }else{
                    $incomeDatewise = 0;
                }

                if(isset($newWeeklyArr[$dateSearch]['expenses'])){
                    $expenditureDatewise = $newArr[$dateSearch]['expenses']['total'];
                }else{
                    $expenditureDatewise = 0;
                }
                $business_millage = 0;
                $personal_millage = 0;
            }else{
                $incomeDatewise = 0;
                $expenditureDatewise = 0;
                $business_millage = 0;
                $personal_millage = 0;
            }
            $profiltoftheday = $incomeDatewise-$expenditureDatewise;
            if($profiltoftheday>$maxdailyProfilt){
                $maxdailyProfilt = $profiltoftheday;
            }
            $weeklydays[] = array('displayDate'=>$date->format('D j'),'date'=>$dateSearch,'income'=>number_format($incomeDatewise,2),'enpense'=>number_format($expenditureDatewise,2),'business_millage'=>$business_millage,'personal_millage'=>$personal_millage,'profit'=>number_format($profiltoftheday,2,'.',''));
            $date->addDays(1);
        }
        for($k=0;$k<7;$k++){
            if($weeklydays[$k]['profit'] <= 0 )
            {
                $weeklydays[$k]['profit'] = number_format(0,9);
            }else{
                $weeklyProfit  = $weeklydays[$k]['profit'];
                $weeklydays[$k]['profit'] = number_format($weeklyProfit/$maxdailyProfilt,9,'.','');
            }
        }
        $currentmonthgraphdata = $weeklydays;
        return array('income'=>number_format($currentmonthincome,2),'expense'=>number_format($currentmonthexpenditure,2),'business_millage'=>$currentmonthbusiness_millage,'personal_millage'=>$currentmonthpersonal_millage,'profit'=>number_format(($currentmonthincome-$currentmonthexpenditure),2),'start-date'=>$firstdayofmonth,'end-date'=>$lastdayofmonth,'months'=>$months,'currentmonthgraphdata'=>$currentmonthgraphdata);
    }

    public function calendar($user_id){
        $today = Carbon::now();
        $days = [];
        for($i=0;$i<12;$i++){
            $month = $today->format('m');
            $year = $today->format('Y');
            $date = $today->format('d');
            $daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year)); ;
            
            $monthlyProfilt=0;
            for($j=0;$j<$daysInMonth;$j++){
                $profiltoftheday = 0;
                $date = $year.'-'.$month.'-'.($j+1);
                $dateObj = Carbon::parse($date);
                $dateSearch = $dateObj->format('Y-m-d');
                $incomeDatewise = $this->getTransactionTotal($user_id,'income',$dateSearch.'%');
                $expenditureDatewise = $this->getTransactionTotal($user_id,'expenses',$dateSearch.'%');
                $profiltoftheday = $incomeDatewise-$expenditureDatewise;
                $monthlyProfilt = $monthlyProfilt + $profiltoftheday;
                $days[] = array('displayDate'=>$dateObj->format('D j'),'date'=>$dateSearch,'income'=>number_format($incomeDatewise,2),'enpense'=>number_format($expenditureDatewise,2),'profit'=>number_format($profiltoftheday,2));
            }
            $today->subMonth();
        }
        return $days;
    }

    protected function getTransactionTotal($user_id,$type,$dateString){
        $transactionTotal = DB::table('transactions')
                    ->where('user_id',$user_id)
                    ->where('type',$type)
                    ->where('transaction_date', 'like', $dateString)
                    ->sum('amount');
        return $transactionTotal;
    }
    
    protected function getMillageTotal($user_id,$type,$dateString){
        if($type == 'business_millage'){
            $transactionTotal = DB::table('millages')
                    ->where('user_id',$user_id)
                    ->where('millage_date', 'like', $dateString)
                    ->sum('business_millage');
            return $transactionTotal;
        }elseif($type == 'personal_millage'){
            $transactionTotal = DB::table('millages')
                    ->where('user_id',$user_id)
                    ->where('millage_date', 'like', $dateString)
                    ->sum('personal_millage');
            return $transactionTotal;
        }
        
    }
    public function getTransactions($dateString,$user_id){
        $transactions = Transaction::join('category_lists','category_lists.id','=','transactions.category_list_id')
        ->selectRaw("transactions.id,transactions.title,transactions.user_id,transactions.amount,transactions.type,IFNULL(null,CONCAT('https://storage.googleapis.com/taxitax/transaction_images/',transactions.document)) as document,transactions.status,transactions.paymentmethod,transactions.transaction_date as transaction_date,category_lists.title as catecory_name,transactions.category_list_id,transactions.is_recurring,transactions.recurring_period")
                    ->where('transactions.user_id',$user_id)
                    ->where('transactions.transaction_date', 'like', $dateString.'%')
                    ->get();
        return $transactions;
    }

    public function getReport(Request $request){
        $today = Carbon::now();
        $input = $request->all();
        $fromDate = explode("-",$input['date_from']);
        $toDate = explode("-",$input['date_to']);

        if(isset($input['date_to'])){
            $dateTo = $toDate[2].'-'.$toDate[1].'-'.$toDate[0].' 23:59:59';
        }else{
            $dateTo = $today->format('Y').'-'.$today->format('m').'-'.$today->format('d').' 23:59:59';;
        }
        if(isset($input['date_from'])){
            $dateFrom = $fromDate[2].'-'.$fromDate[1].'-'.$fromDate[0].' 00:00:00';
        }else{
            $dateFrom = $today->subDays(30)->format('Y-m-d').' 00:00:00';;
        }
        
        $user_id = $input['user_id'];
        $type = $input['type'];
        $transactions = Transaction::join('category_lists','category_lists.id','=','transactions.category_list_id')
        ->selectRaw("FORMAT(sum(transactions.amount),2) as total,category_lists.title as catecory_name")
        ->where('transactions.user_id',$user_id)
        ->whereBetween('transactions.transaction_date', [$dateFrom,$dateTo])
        ->where('transactions.type',$type)
        ->groupBy('category_lists.id', 'category_lists.title')
        ->get();
        return $transactions;
    }
}
