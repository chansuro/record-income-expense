<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Wfh;
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
            $input['repeat_on'] = implode(',', $input['repeat_on']); 
            $reminder = Wfh::create($input);
            return ['response'=>$input, 'msg'=>'WFH expenses added successfully!'];
        }
    }

    function getData($user_id){
        $wfh = Wfh::where('user_id',$user_id)->first();
        return ['response'=>true, 'data'=>$wfh];
    }

    function calculateBillPercentage(Request $request){
        $input = $request->all();
        $userid = $input['user_id']; // You can modify this to get the type from input if needed
        $calculator = new BillPercentageCalculatorService();
        $percentage = $calculator->calculate($userid );
        return ['response'=>true, 'percentage'=>$percentage];
    }
}
