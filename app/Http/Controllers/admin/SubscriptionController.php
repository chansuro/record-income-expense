<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Billing;
use App\Models\User;

class SubscriptionController extends Controller
{
    //
    public function index(Request $request){
        $input = $request->all();
        if(isset($request->u)){
            $user = User::where('id',$request->u)->first();
        }
        $query = Billing::where('user_id',$input['u']);
        $query->when($request->has('start_date') && $request->input('end_date'), function ($query) use ($request) {
            $FromDateArr = explode("-",$request->start_date);
            $FromDate = $FromDateArr[2].'-'.$FromDateArr[1].'-'.$FromDateArr[0].' 00:00:00';

            $ToDateArr = explode("-",$request->end_date);
            $ToDate = $ToDateArr[2].'-'.$ToDateArr[1].'-'.$ToDateArr[0].' 23:59:59';
            $query->whereBetween('billing.invoice_date', [strtotime($FromDate), strtotime($ToDate)]);
        });
        $query->when($request->has('start_date'), function ($query) use ($request) {
            $FromDateArr = explode("-",$request->start_date);
            $FromDate = $FromDateArr[2].'-'.$FromDateArr[1].'-'.$FromDateArr[0].' 00:00:00';
            $query->where('billing.invoice_date', '>=', strtotime($FromDate));
        });
        $query->when($request->has('to_date'), function ($query) use ($request) {
            $ToDateArr = explode("-",$request->end_date);
            $ToDate = $ToDateArr[2].'-'.$ToDateArr[1].'-'.$ToDateArr[0].' 23:59:59';
            $query->where('billing.invoice_date', '<=', strtotime($FromDate));
        });
        
        $Subscription = $query->paginate(20);
        return view('admin.subscriptions',['subscription'=>$Subscription,'user'=>(isset($user))?$user: null] );
    }
}
