<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use App\Services\TwilioService;
use App\Models\EmailTemplate;
use App\Mail\AppMail;
use Illuminate\Support\Facades\Mail;
use Google\Cloud\Storage\StorageClient;

class userAuthController extends Controller
{
    protected $twilioService;

    public function __construct(TwilioService $twilioService)
    {
        $this->twilioService = $twilioService;
    }
    
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
                    $otp = $this->getotp($input['phone']);
                }
            }
            
        }
        //return ['response'=>true,'token'=>$token,'data'=>$user,'otp'=>$otp];
        return ['response'=>true,'otp'=>$otp];
    }

    protected function getotp($recipient_phone_numbers){
        $randomNumber = rand(1012, 9001);
        $message = "Your one-time password (OTP) for taxitax is: {$randomNumber}";
        $this->twilioService->sendSms('+44'.$recipient_phone_numbers, $message);
        return $randomNumber;
    }

    protected function getemailotp($user,$emailtype){
        $randomNumber = rand(1012, 9001);
        $EmailTemplate = EmailTemplate::where('key',$emailtype)->first();
        $subject = $EmailTemplate->subject;
        $body = $EmailTemplate->body;
        $emailKeywordsArr = config('app.email_template_var');
        for($i=0;$i<count($emailKeywordsArr);$i++){
            if($emailKeywordsArr[$i] == '[NAME]'){
                $subject = str_replace('[NAME]',$user->name,$subject);
                $body = str_replace('[NAME]',$user->name,$body);
            }
            if($emailKeywordsArr[$i] == '[OTP_CODE]'){
                $subject = str_replace('[OTP_CODE]',$randomNumber,$subject);
                $body = str_replace('[OTP_CODE]',$randomNumber,$body);
            }
        }
        $to = $user->email;
        $mail = new AppMail($subject,$body);
        Mail::to($to)->send($mail);
        return $randomNumber;
    }

    function resendotp(Request $request){
        $rules = array(
            'phone'=> 'required | min:10 | max:10',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $input = $request->all();
            $otp = $this->getotp($input["phone"]);
        }
        return ['response'=>true,'otp'=>$otp];
    }

    function updatepassword(Request $request){
        $rules = array(
            'user_id'=> 'required',
            'oldpassword'=> 'required',
            'password'=> 'required | min:8',
            'confirmpassword'=> 'required | min:8',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $user = User::where('id',$request->user_id)->first();
            if(!$user->id){
                 return ['response'=>false, 'msg'=>'No data found!'];
            }elseif(!Hash::check($request->oldpassword,$user->password)){
                return ['response'=>false, 'msg'=>'Please enter your valid current password!'];
            }elseif($request->password != $request->confirmpassword){
                return ['response'=>false, 'msg'=>'New password and confirm password are not same!'];
            }else{
                $input = $request->all();
                $updateinput["password"] = \bcrypt($input["password"]); 
                User::where('id',$input['user_id'])->update($updateinput);
                return ['response'=>true, 'msg'=>'Password edited successfully!'];
            }
        }
    }

    function getprofile($user_id){
        $user = User::selectRaw("id,name,email,phone,IFNULL(null,CONCAT('https://storage.googleapis.com/taxitax/avatar_images/',avatar)) as avatar,isemailverified,IF(created_at > CURDATE() - INTERVAL 3 DAY, true, false) AS is_trial,created_at,DATE_ADD(created_at, INTERVAL 3 DAY) AS trial_expiry_date")->where('id',$user_id)->first();
        if($user->is_trial){
            $timestamp = strtotime($user->trial_expiry_date);
            $formattedDate = date('d/m/Y', $timestamp);
            $user->trialMessage = "Your free 3 days trial ends {$formattedDate} and billing of £5.95/month will commence thereafter.";
        }
        unset($user->trial_expiry_date);
        unset($user->created_at);
        return ['response'=>true, 'data'=>$user];
    }

    function updateprofile(Request $request){
        $rules = array(
            'user_id'=> 'required',
            'name'=>'required | min:2',
            'email'=> 'email | required',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $user = User::where('id',$request->user_id)->first();
            if(!$user->id){
                 return ['response'=>false, 'msg'=>'No data found!'];
            }else{
                $input = $request->all();
                if($user->email != $input["email"]){
                    $userEmail = User::where('email',$request->email)->where('id','!=',$request->user_id)->first();
                    if($userEmail){
                        return ['response'=>false, 'msg'=>'Email already exists. Please use another email!'];
                    }
                }
                $updateinput["name"] = $input["name"]; 
                $updateinput["email"] = $input["email"]; 
                User::where('id',$input['user_id'])->update($updateinput);
                return ['response'=>true, 'msg'=>'Profile edited successfully!'];
            }
        }
    }

    function updatemobile(Request $request){
        $rules = array(
            'user_id'=> 'required',
            'phone'=> 'required | min:10 | max:10',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $user = User::where('id',$request->user_id)->first();
            if(!$user->id){
                 return ['response'=>false, 'msg'=>'No data found!'];
            }else{
                $input = $request->all();
                $updateinput["phone"] = $input["phone"]; 
                User::where('id',$input['user_id'])->update($updateinput);
                return ['response'=>true, 'msg'=>'Mobile edited successfully!'];
            }
        }
    }

    function updatefcmtoken(Request $request){
        $rules = array(
            'user_id'=> 'required',
            'fcm_token'=> 'required',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $user = User::where('id',$request->user_id)->first();
            if(!$user->id){
                 return ['response'=>false, 'msg'=>'No data found!'];
            }else{
                $input = $request->all();
                $updateinput["fcm_token"] = $input["fcm_token"]; 
                User::where('id',$input['user_id'])->update($updateinput);
                return ['response'=>true, 'msg'=>'Token updated successfully!'];
            }
        }
    }

    function updateavatar(Request $request){
        $rules = array(
            'user_id'=> 'required',
            'avatar' => 'image|mimes:jpg,png,jpeg,gif,svg|max:2048',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $user = User::where('id',$request->user_id)->first();
            if(!$user->id){
                 return ['response'=>false, 'msg'=>'No data found!'];
            }else{
                $input = $request->all();
                putenv('GOOGLE_APPLICATION_CREDENTIALS='.storage_path(env('GOOGLE_CLOUD_KEY_FILE')));
                $storage = new StorageClient();
                $bucket = $storage->bucket(env('GOOGLE_CLOUD_STORAGE_BUCKET'));
                if($user->avatar!=''){
                    $object = $bucket->object('avatar_images/'.$user->avatar);
                    $object->delete();
                }
                if($request->hasFile('avatar')) {
                    $filename = $request->file('avatar')->getClientOriginalName(); // get the file name
                    $getfilenamewitoutext = pathinfo($filename, PATHINFO_FILENAME); // get the file name without extension
                    $getfileExtension = $request->file('avatar')->getClientOriginalExtension(); // get the file extension
                    $createnewFileName = time().'_'.str_replace(' ','_', $getfilenamewitoutext).'.'.$getfileExtension; // create new random file name
                    //$request->document->move(public_path('transaction_images'), $createnewFileName); //local path
                    $request->avatar->move('avatar_images', $createnewFileName);
                    $objectName = $createnewFileName;
                    $filePath = public_path('avatar_images').'/'.$createnewFileName;
                    $path = $bucket->upload(
                        fopen($filePath, 'r'), // Open the file in read mode
                        [
                            'name' => 'avatar_images/'.$objectName // Set the file name in the bucket
                        ]
                    );
                    $object = $bucket->object('avatar_images/'.$objectName);
                    $object->update([
                        'acl' => [
                            ['entity' => 'allUsers', 'role' => 'READER']
                        ]
                    ]);
                    $image_path = public_path('avatar_images')."/{$createnewFileName}";
                    if (File::exists($image_path)) {
                        unlink($image_path);
                    }
                    $updateinput['avatar'] = $createnewFileName;
                    User::where('id',$input['user_id'])->update($updateinput);
                }
                return ['response'=>true, 'msg'=>'Avatar updated successfully!'];
            }
        }
    }

    function contactus(Request $request){
        $rules = array(
            'name'=>'required | min:2',
            'email'=> 'email | required',
            'phone'=> 'required | min:10 | max:10',
            'message'=> 'required',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $input = $request->all();
            $EmailTemplate = EmailTemplate::where('key','ContactUs')->first();
            $subject = $EmailTemplate->subject;
            $body = $EmailTemplate->body;
            $emailKeywordsArr = config('app.email_template_var');
            for($i=0;$i<count($emailKeywordsArr);$i++){
                if($emailKeywordsArr[$i] == '[NAME]'){
                    $subject = str_replace('[NAME]',$input['name'],$subject);
                    $body = str_replace('[NAME]',$input['name'],$body);
                }
                if($emailKeywordsArr[$i] == '[EMAIL]'){
                    $subject = str_replace('[EMAIL]',$input['email'],$subject);
                    $body = str_replace('[EMAIL]',$input['email'],$body);
                }
                if($emailKeywordsArr[$i] == '[PHONE]'){
                    $subject = str_replace('[PHONE]',$input['phone'],$subject);
                    $body = str_replace('[PHONE]',$input['phone'],$body);
                } 
                if($emailKeywordsArr[$i] == '[MESSAGE]'){
                    $subject = str_replace('[MESSAGE]',$input['message'],$subject);
                    $body = str_replace('[MESSAGE]',$input['message'],$body);
                }
            }
            $to = config('app.admin_email');;  
            $mail = new AppMail($subject,$body);
            Mail::to($to)->send($mail);
            return ['response'=>true, 'msg'=>'Email sent to admin successfully!'];
        }

    }

    function resetpasswordrequest(Request $request){
        $rules = array(
            'email'=> 'email | required'
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $user = User::where('email',$request->email)->where('status',1)->first();
            if($user){
                $otp = $this->getemailotp($user,'ForgotPassword');
                return ['response'=>true, 'msg'=>'Reset password email sent.','otp'=>$otp,'user_id'=>$user->id];
            }else{
                return ['response'=>false, 'msg'=>'No data found!'];
            }
        }
    }

    function updateforgotpassword(Request $request){
        $rules = array(
            'user_id'=> 'required',
            'password'=> 'required | min:8',
            'confirmpassword'=> 'required | min:8',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $user = User::where('id',$request->user_id)->first();
            if(!$user->id){
                 return ['response'=>false, 'msg'=>'No data found!'];
            }elseif($request->password != $request->confirmpassword){
                return ['response'=>false, 'msg'=>'New password and confirm password are not same!'];
            }else{
                $input = $request->all();
                $updateinput["password"] = \bcrypt($input["password"]); 
                User::where('id',$input['user_id'])->update($updateinput);
                return ['response'=>true, 'msg'=>'Password edited successfully!'];
            }
        }
    }

    function verifyemail(Request $request){
        $rules = array(
            'email'=> 'email | required'
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $user = User::where('email',$request->email)->where('status',1)->first();
            if($user){
                $otp = $this->getemailotp($user,'VerifyEmail');
                return ['response'=>true, 'msg'=>'Verification email sent.','otp'=>$otp,'user_id'=>$user->id];
            }else{
                return ['response'=>false, 'msg'=>'No data found!'];
            }
        }
    }

    function updateemailverified(Request $request){
        $rules = array(
            'user_id'=> 'required',
            'email'=> 'email | required'
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return ['response'=>false, 'msg'=>$validation->errors()];
        }else{
            $user = User::where('email',$request->email)->where('status',1)->where('id',$request->user_id)->first();
            if($user){
                $updateinput["isemailverified"] = '1'; 
                User::where('id',$request->user_id)->update($updateinput);
                return ['response'=>true, 'msg'=>'Email verification successful!'];
            }else{
                return ['response'=>false, 'msg'=>'No data found!'];
            }
        }
    }
}
