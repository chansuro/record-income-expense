<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    //This method will show admin login page
    public function index(){
        return \view('admin.login');
    }
    //This method will authenticate admin
    public function authenticate(Request $request){
        $rules = array(
            'email'=>'required|email' ,
            'password'=> 'required',
        );
        $validation = Validator::make($request->all(), $rules);

        if($validation->fails()){
            return redirect()->route('admin.login')->withInput()->withErrors($validation);
        }else{
            if(Auth::guard('admin')->attempt(['email'=>$request->email,'password'=>$request->password])){
                if(Auth::guard('admin')->user()->role == 'admin'){
                    return redirect()->route('admin.dashboard');
                }else{
                    Auth::guard('admin')->logout();
                    return redirect()->route('admin.login')->withInput()->with('error','You are not authorised to access this page.');
                }
                
            }else{
                return redirect()->route('admin.login')->withInput()->with('error','The combination of email and password is not correct.');
            }
        }
    }

    public function logout(){
        Auth::guard('admin')->logout();
        return redirect()->route('admin.login');
    }
}
