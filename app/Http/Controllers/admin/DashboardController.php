<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Billing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // this function will show the admin dashboard
    public function index(){
        $today = Carbon::now();
        $firstdayofmonth = Carbon::parse($today->format('Y').'-'.$today->format('m').'-1 00:00:00')->format('d-M-Y H:is');
        $lastdayofmonth = Carbon::parse($today->format('Y').'-'.$today->format('m').'-'.$today->format('d').' 23:59:59')->format('d-M-Y H:i:s');
        $monthlyTotalSubscription = Billing::where('invoice_status','paid')->whereBetween('invoice_date',[strtotime($firstdayofmonth),strtotime($lastdayofmonth)])->sum('amount');

        $months = [];
        $totalearnings = 0;
        for($i=0;$i<12;$i++){
            $month = $today->format('m');
            $year = $today->format('Y');
            $date = $today->format('d');
            $daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year));
            $firstdayMonth = Carbon::parse($today->format('Y').'-'.$today->format('m').'-1 00:00:00')->format('d-M-Y H:is');
            $lastdayMonth = Carbon::parse($today->format('Y').'-'.$today->format('m').'-'.$today->format('d').' 23:59:59')->format('d-M-Y H:i:s');
            $monthlyTotalSubscription = Billing::where('invoice_status','paid')->whereBetween('invoice_date',[strtotime($firstdayMonth),strtotime($lastdayMonth)])->sum('amount');
            $totalearnings = $totalearnings+($monthlyTotalSubscription/100);
            $monthShortName[]  = $today->format('M');
            $monthearning[]  = number_format(($monthlyTotalSubscription/100),2,'.','');

            $months[$i] = array('displayMonth'=>$today->format('M'),'month'=>$today->format('m'),'year'=>$today->format('Y'),'monthlyprofit'=>number_format(($monthlyTotalSubscription/100),2,'.',''));
            $today->subMonth();
        }

        $totalUsers = User::where('role','customer')->where('status','1')->count();

        $subscriptionpaid = User::join('billings', 'users.id', '=', 'billings.user_id')->distinct()->where('billings.invoice_status','paid')->where('billings.invoice_date','>=',strtotime($firstdayMonth))
                ->count();
        $subscriptionupcoming = DB::select("select count(users.id) as totalUpcoming from users left join billings on billings.user_id=users.id and billings.invoice_status ='paid' where users.status=1 and users.role='customer' and billings.id IS NULL");
        $subscriptionfailed = User::join('billings', 'users.id', '=', 'billings.user_id')->distinct()->where('billings.invoice_status','failed')->where('billings.invoice_date','>=',strtotime($firstdayMonth))
                ->count();

        $subscriptionPlanName = \env('SUBSCRIPTION_PLAN_NAME');
        $subscriptionPlanName = "App Tax Subscription";
        return view('admin.dashboard',[
            'monthlyTotalSubscription'=>$monthlyTotalSubscription,
            'yearlyearning'=>$totalearnings,
            'monthName'=>implode('#', $monthShortName),
            'monthEarning'=>implode('#', $monthearning),
            'totalusers'=>$totalUsers,
            'subscriptionpaid'=>$subscriptionpaid,
            'subscriptionupcoming'=>$subscriptionupcoming[0]->totalUpcoming,
            'subscriptionfailed'=>$subscriptionfailed,
            'planname' => $subscriptionPlanName,
        ]);
    }
}
