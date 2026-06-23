<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Depreciation;
use App\Models\Transaction;

class DepreciationController extends Controller
{
    function setData(Request $request){
        $rules = array(
            'user_id'=> 'required',
            'vehicle_cost'=> 'required|numeric',
            'emission_type'=> 'required',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $input = $request->all();
            //$input['repeat_on'] = implode(',', $input['repeat_on']); 
            $user_id = $input['user_id'];
            $depretiotion = Depreciation::where('user_id',$user_id)->first();
            if($depretiotion){
                $depretiotion->update($input);
                return ['response'=>$input, 'msg'=>'Depreciation expenses added successfully!'];
            }
            $reminder = Depreciation::create($input);
            return ['response'=>$input, 'msg'=>'Depreciation expenses added successfully!'];
        }
    }
    function getData(Request $request){
        $rules = array(
            'user_id'=> 'required',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $input = $request->all();
            $user_id = $input['user_id'];
            $depretiotion = Depreciation::where('user_id',$user_id)->first();
            if($depretiotion){
                $depretiation_cost = $depretiotion->vehicle_cost * ($depretiotion->dep_percentage/100);
                $depretiation_monthlycost = $depretiation_cost / 12;
                $depretiotion->totalmonthlycost = number_format($depretiation_monthlycost, 2, '.', '');
                $depretiotion->totalyearlycost = number_format($depretiation_cost, 2, '.', '');
                $depretiotion->depretiation_category_id = 140;
                return ['response'=>$depretiotion, 'msg'=>'Depreciation expenses data retrieved successfully!'];
            }else{
                return ['response'=>false, 'msg'=>'No Depreciation expenses data found!'];
            }
        }
    }

    function calculateDepreciation($user_id){
        $depretiotion = Depreciation::where('user_id',$user_id)->first();

        if($depretiotion->id > 0){
             $asset_cost = $depretiotion->asset_cost;
             $emission_type = $depretiotion->emission_type;
             // Assuming a simple straight-line depreciation for demonstration
             $depreciation_rate = $depretiotion->dep_percentage;
             $depreciation_expense = $asset_cost * ($depreciation_rate/100);
             $asset_cost -= $depreciation_expense; // Reduce the asset cost by the depreciation expense
             $depretiotion->save([
                'asset_cost' => $asset_cost,
             ]);   
             return ['response'=>true, 'msg'=>'Depreciation expense calculated successfully!'];
        }else{
            return ['response'=>false, 'msg'=>'No Depreciation expenses data found!'];
        }
    }
    function iswdaalreadyset($user_id){
        $wda = Transaction::where('user_id',$user_id)->where('type','=','expenses')->where('is_recurring','=','Y')->where('category_list_id','=','140')->first();
        if($wda){
            return ['response'=>true, 'msg'=>'Depreciation expenses already set!'];
        }else{
            return ['response'=>false, 'msg'=>'Depreciation expenses not set yet!'];
        }
    }
}
