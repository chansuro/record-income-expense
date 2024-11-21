<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    //
    public function index($user_id){
        $today = Carbon::now();
        $dateSearchCurrent = $today->format('Y').'-'.$today->format('m').'-%';
        //calculation of last 12 months
        $months = [];
        for($i=0;$i<12;$i++){
            $month = $today->format('m');
            $year = $today->format('Y');
            $daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year)); ;
            $days = [];
            for($j=0;$j<$daysInMonth;$j++){
                $date = $year.'-'.$month.'-'.($j+1);
                $dateObj = Carbon::parse($date);
                $dateSearch = $dateObj->format('Y-m-d');
                $incomeDatewise = $this->getTransactionTotal($user_id,'income',$dateSearch.'%');
                $expenditureDatewise = $this->getTransactionTotal($user_id,'expenses',$dateSearch.'%');
                $days[$j] = array('displayDate'=>$dateObj->format('D j'),'income'=>$incomeDatewise,'enpense'=>$expenditureDatewise,'profit'=>$incomeDatewise-$expenditureDatewise);
            }
            $months[$i] = array('displayMonth'=>$today->format('M'),'month'=>$today->format('m'),'year'=>$today->format('Y'),'days'=>$days);
            $today->subMonth();
        }
        $income = $this->getTransactionTotal($user_id,'income',$dateSearchCurrent);
        $expenditure = $this->getTransactionTotal($user_id,'expenses',$dateSearchCurrent);
        //
        return array('income'=>$income,'expense'=>$expenditure,'profit'=>$income-$expenditure,'months'=>$months);
    }

    protected function getTransactionTotal($user_id,$type,$dateString){
        $transactionTotal = DB::table('transactions')
                    ->where('user_id',$user_id)
                    ->where('type',$type)
                    ->where('created_at', 'like', $dateString)
                    ->sum('amount');
        return $transactionTotal;
    }
}
