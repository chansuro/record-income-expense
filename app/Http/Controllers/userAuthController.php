<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class userAuthController extends Controller
{
    //
    function login(Request $request){
        $rules = array(
            'email'=> 'required',
            'password'=> 'required'
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $user = User::where('email',$request->email)->first();
            if(!$user || !Hash::check($request->password,$user->password)){
                return ['response'=>false, 'msg'=>'The combination of username and password is not found!'];
            }else{
                $token = $user->createToken("taxiApp")->plainTextToken;
            }
        }
        return ['response'=>true, 'user'=>$user,'token'=>$token];
    }

    function signup(Request $request){
        $rules = array(
            'name'=>'required | min:2',
            'email'=> 'email | required',
            'password'=> 'required | min:8',
            'phone'=> 'required | min:10 | max:10',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $userEmail = User::where('email',$request->email)->first();
            if($userEmail){
                return ['response'=>false, 'msg'=>'Email already exists. Please use another email!'];
            }else{
                $userPhone = User::where('phone',$request->phone)->first();
                if($userPhone){
                    return ['response'=>false, 'msg'=>'Phone number already exists. Please use another phone number!'];
                }else{
                    $input = $request->all();
                    $input["password"] = \bcrypt($input["password"]); 
                    $input["status"] = 1; 
                    $user = User::create($input);
                    $token = $user->createToken("taxiApp")->plainTextToken;
                    $user["name"] = $user->name;
                    $user["email"] = $user->email;
                    $user["status"] = $user->status;
                    $user["phone"] = $user->phone;
                }
            }
            
        }
        return ['response'=>true,'token'=>$token,'data'=>$user];
    }
}
