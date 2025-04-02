<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Reminder;

class ReminderController extends Controller
{
    //
    function setReminder(Request $request){
        $rules = array(
            'user_id'=> 'required',
            'is_alerm'=> 'required',
            'reminder_time'=> 'required',
            'repeat_on'=> 'required'
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $input = $request->all();
            $input['repeat_on'] = implode(',', $input['repeat_on']); 
            $reminder = Reminder::create($input);
            return ['response'=>$input, 'msg'=>'Reminder added successfully!'];
        }
    }

    function getReminder($user_id){
        $reminder = Reminder::where('user_id',$user_id)->first();
        $repeatonarr = [];
        if($reminder){
            foreach(explode(',',$reminder->repeat_on) as $k=>$v)
            {
                $repeatonarr[] = ['day'=>$v];
            }
        }
        return ['response'=>true, 'data'=>$reminder,'repeatonarr'=>$repeatonarr];
    }
    function updateReminder(Request $request){
        $rules = array(
            'user_id'=> 'required',
            'is_alerm'=> 'required',
            'reminder_time'=> 'required',
            'repeat_on'=> 'required'
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $input = $request->all();
            $reminder = Reminder::where('id',$input['id'])->where('user_id',$input['user_id'])->first();

            if($reminder->id >0){
                $inputUpdate['is_alerm'] = $input['is_alerm'];
                $inputUpdate['reminder_time'] = $input['reminder_time'];
                $inputUpdate['repeat_on'] = implode(',', $input['repeat_on']);
                $reminder = Reminder::where('id',$input['id'])->where('user_id',$input['user_id'])->update($inputUpdate);
                return ['response'=>true, 'msg'=>'Reminder edited successfully!'];
            }else{
                return ['response'=>false, 'msg'=>'No data found!'];
            }
        }
    }
    public function destroy(Request $request)
    {
        $rules = array(
            'id'=>'required',
            'user_id'=> 'required'
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $input = $request->all();
            $reminder = Reminder::where('id',$input['id'])->where('user_id',$input['user_id'])->first();
            if($reminder->id >0){
                $reminder = Reminder::where('id',$input['id'])->where('user_id',$input['user_id'])->delete();
                return ['response'=>true, 'msg'=>'Reminder deleted successfully!'];
            }else{
                return ['response'=>false, 'msg'=>'No data found!'];
            }
        }
    }
}
