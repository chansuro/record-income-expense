<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\EmailTemplate;
use App\Mail\AppMail;
use Illuminate\Support\Facades\Mail;
use Google\Cloud\Storage\StorageClient;
use App\Models\Transaction;
use App\Models\Millage;

class LoginController extends Controller
{
    //This method will show admin login page
    public function index(){
        return \view('admin.login');
    }
    //This method will show user login page
    public function indexuserlogin(){
        return \view('admin.indexuserlogin');
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
    //This method will authenticate user
    public function authenticateuser(Request $request){
        $rules = array(
            'email'=>'required|email' ,
            'password'=> 'required',
        );
        $validation = Validator::make($request->all(), $rules);

        if($validation->fails()){
            return redirect()->route('general.login')->withInput()->withErrors($validation);
        }else{
            if(Auth::guard('web')->attempt(['email'=>$request->email,'password'=>$request->password])){
                if(Auth::guard('web')->user()->role == 'customer'){
                    //return redirect()->route('admin.dashboard');
                    $userId = Auth::guard('web')->user()->id;
                    $user = Auth::guard('web')->user();
                    // delete user avatar
                    putenv('GOOGLE_APPLICATION_CREDENTIALS='.storage_path(config('services.googlecloud.key')));
                    $storage = new StorageClient();
                    $bucket = $storage->bucket(config('services.googlecloud.bucket'));
                    if($user->avatar!=''){
                        $object = $bucket->object('avatar_images/'.$user->avatar);
                        if($object->exists()){
                            $object->delete();
                        }
                    }
                    $updateinput["status"] = 3; // soft deleted user
                    $updateinput["suspend_reason"] = 'Other'; 
                    $updateinput["avatar"] = ''; 
                    User::where('id',$userId)->update($updateinput);
                    // remove transactions
                    $transactions = Transaction::where('user_id',$userId)->get();
                    for($i=0; $i<sizeof($transactions);$i++)
                    {
                        $transaction = $transactions[0];
                        if($transaction->document !=''){
                            putenv('GOOGLE_APPLICATION_CREDENTIALS='.storage_path(config('services.googlecloud.key')));
                            $storage = new StorageClient();
                            $bucket = $storage->bucket(config('services.googlecloud.bucket'));

                            if($transaction->document !='' && file_exists(public_path('transaction_images/'.$transaction->document))){
                                $object = $bucket->object('transaction_images/'.$transaction->document);
                                $object->delete();
                            }
                        }
                    }
                    $Transaction = Transaction::where('user_id',$userId)->delete();
                    
                    // remove millage
                    $millages = Millage::where('user_id',$userId)->get();
                    for($i=0; $i<sizeof($millages);$i++)
                    {
                        $millage = $millages[0];
                        putenv('GOOGLE_APPLICATION_CREDENTIALS='.storage_path(config('services.googlecloud.key')));
                        $storage = new StorageClient();
                        $bucket = $storage->bucket(config('services.googlecloud.bucket'));

                        if($millage->document !='' && file_exists(public_path('millage_images/'.$millage->document))){
                            $object = $bucket->object('millage_images/'.$millage->document);
                            $object->delete();
                        }
                    } 
                    $Millage = Millage::where('user_id',$userId)->delete(); 
                    $EmailTemplate = EmailTemplate::where('key','AccountDelete')->first();
                    $subject = $EmailTemplate->subject;
                    $body = $EmailTemplate->body;
                    $emailKeywordsArr = config('app.email_template_var');
                    for($i=0;$i<count($emailKeywordsArr);$i++){
                        if($emailKeywordsArr[$i] == '[NAME]'){
                            $subject = str_replace('[NAME]',$user->name,$subject);
                            $body = str_replace('[NAME]',$user->name,$body);
                        }
                        if($emailKeywordsArr[$i] == '[EMAIL]'){
                            $subject = str_replace('[EMAIL]',$user->email,$subject);
                            $body = str_replace('[EMAIL]',$user->email,$body);
                        }
                        if($emailKeywordsArr[$i] == '[PHONE]'){
                            $subject = str_replace('[PHONE]',$user->phone,$subject);
                            $body = str_replace('[PHONE]',$user->phone,$body);
                        } 
                    }
                    $to = $user->email;  
                    $mail = new AppMail($subject,$body);
                    Mail::to($to)->send($mail);

                    Auth::guard('web')->logout();
                    return redirect()->route('general.login')->withInput()->with('success','All the data related to your account deleted successfully.');
                }else{
                    Auth::guard('web')->logout();
                    return redirect()->route('general.login')->withInput()->with('error','You are not authorised to access this page.');
                }
                
            }else{
                return redirect()->route('general.login')->withInput()->with('error','The combination of email and password is not correct.');
            }
        }
    }

    public function logout(){
        Auth::guard('admin')->logout();
        return redirect()->route('admin.login');
    }
}
