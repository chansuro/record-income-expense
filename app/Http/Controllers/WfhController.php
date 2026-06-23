<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Wfh;
use App\Models\Transaction;
use App\Services\BillPercentageCalculatorService;

class WfhController extends Controller
{
    //
    function setData(Request $request){
        $rules = array(
            'user_id'=> 'required',
            'space'=> 'required',
            'space_occupied'=> 'required',
            'space_unit'=> 'required',
            'time_spend'=> 'required',
            'time_spend_unit'=> 'required',
            'expense_on'=> 'required',
            'elecricity_bill'=> 'required|numeric',
            'internet_bill'=> 'required|numeric',
            'other_bill'=> 'required|numeric',
            'heating_bill'=> 'required|numeric',
            'council_tax_bill'=> 'required|numeric',
            'rent_or_mortgage'=> 'required|numeric',
            'phone_bill'=> 'required|numeric',
            'services_bill'=> 'required|numeric',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $input = $request->all();
            //$input['repeat_on'] = implode(',', $input['repeat_on']); 
            $user_id = $input['user_id'];
            $wfh = Wfh::where('user_id',$user_id)->first();
            if($wfh){
                $wfh->update($input);
                return ['response'=>$input, 'msg'=>'WFH expenses updated successfully!'];
            }
            $reminder = Wfh::create($input);
            return ['response'=>$input, 'msg'=>'WFH expenses added successfully!'];
        }
    }

    function getData($user_id){
        $wfh = Wfh::where('user_id',$user_id)->first();
        $space = $wfh->space;
        $space_occupied = $wfh->space_occupied;
        $space_percentage = ($space_occupied / $space) * 100;
        $wfh->space_percentage = number_format($space_percentage, 2, '.', '');
        $time_spend = $wfh->time_spend;
        $time_spend_percentage = ($time_spend / 7) * 100;
        $wfh->time_spend_percentage = number_format($time_spend_percentage, 2, '.', '');

        $elecricity_bill = $wfh->elecricity_bill;
        $elecricity_bill_percentage = $elecricity_bill * $space_percentage * $time_spend_percentage / 10000;
        $internet_bill = $wfh->internet_bill;
        $internet_bill_percentage = $internet_bill * $space_percentage * $time_spend_percentage / 10000;
        $other_bill = $wfh->other_bill;
        $other_bill_percentage = $other_bill * $space_percentage * $time_spend_percentage / 10000;
        $heating_bill = $wfh->heating_bill;
        $heating_bill_percentage = $heating_bill * $space_percentage * $time_spend_percentage / 10000;
        $council_tax_bill = $wfh->council_tax_bill;
        $council_tax_bill_percentage = $council_tax_bill * $space_percentage * $time_spend_percentage / 10000;
        $rent_or_mortgage = $wfh->rent_or_mortgage;
        $rent_or_mortgage_percentage = $rent_or_mortgage * $space_percentage * $time_spend_percentage / 10000;
        $phone_bill = $wfh->phone_bill;
        $phone_bill_percentage = $phone_bill * $space_percentage * $time_spend_percentage / 10000;
        $services_bill = $wfh->services_bill;
        $services_bill_percentage = $services_bill * $space_percentage * $time_spend_percentage / 10000;

        $wfh->elecricity_bill_percentage = number_format($elecricity_bill_percentage, 2, '.', '');
        $wfh->internet_bill_percentage = number_format($internet_bill_percentage, 2, '.', '');
        $wfh->other_bill_percentage = number_format($other_bill_percentage, 2, '.', '');
        $wfh->heating_bill_percentage = number_format($heating_bill_percentage, 2, '.', '');
        $wfh->council_tax_bill_percentage = number_format($council_tax_bill_percentage, 2, '.', '');
        $wfh->rent_or_mortgage_percentage = number_format($rent_or_mortgage_percentage, 2, '.', '');
        $wfh->phone_bill_percentage = number_format($phone_bill_percentage, 2, '.', '');
        $wfh->services_bill_percentage = number_format($services_bill_percentage, 2, '.', '');
        $total_all_bill_percentage = $elecricity_bill_percentage + $internet_bill_percentage + $other_bill_percentage + $heating_bill_percentage + $council_tax_bill_percentage + $rent_or_mortgage_percentage + $phone_bill_percentage + $services_bill_percentage;
        $wfh->total_allowable_deduction = number_format($total_all_bill_percentage, 2, '.', '');
        $wfh->wfh_category_id = 141;
        return ['response'=>true, 'data'=>$wfh];
    }

    function calculateBillPercentage(Request $request){
        $input = $request->all();
        $userid = $input['user_id']; // You can modify this to get the type from input if needed
        $calculator = new BillPercentageCalculatorService();
        $percentage = $calculator->calculate($userid );
        return ['response'=>true, 'percentage'=>$percentage];
    }

    function iswfhalreadyset($user_id){
        $wfh = Transaction::where('user_id',$user_id)->where('type','=','expenses')->where('is_recurring','=','Y')->where('category_list_id','=','141')->first();
        if($wfh){
            return ['response'=>true, 'msg'=>'WFH expenses already set!'];
        }else{
            return ['response'=>false, 'msg'=>'WFH expenses not set yet!'];
        }
    }
}
