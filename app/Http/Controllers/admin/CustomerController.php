<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Billing;
use App\Models\EmailTemplate;
use App\Mail\AppMail;
use Illuminate\Support\Facades\Mail;
use Stripe\Stripe;
use Stripe\Subscription;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Auth as FirebaseAuth;

class CustomerController extends Controller
{
    //
    public function index(Request $request){
        $query = User::where('role','customer');
        $query->when($request->has('start_date') && $request->input('end_date'), function ($query) use ($request) {
            $query->whereBetween('created_at', [$request->start_date.' 00:00:00', $request->end_date.' 23:59:59']);
        });
        $query->when($request->has('start_date'), function ($query) use ($request) {
            $query->where('created_at', '>=', $request->start_date.' 00:00:00');
        });
        $query->when($request->has('to_date'), function ($query) use ($request) {
            $query->where('created_at', '<=', $request->to_date.' 23:59:59');
        });
        if($request->status!=''){
            $query->when(isset($request->status), function ($query) use ($request) {
                if($request->status == '0'){
                    $query->where('status', 1)->where('created_at', '>=', Carbon::now()->subDays(3));
                }
                else{
                    $query->where('status', $request->status);
                }
                $query->where('status', $request->status);
            });
        }else{
            $query->whereIn('status', [1,3,4,5]);
        }
        $query->when(isset($request->str_search), function ($query) use ($request) {
            $query->where(function ($query) use ($request) {
                $query->where('name','like', '%' .  $request->str_search . '%');
                $query->orWhere('email','like', '%' .  $request->str_search . '%');
                $query->orWhere('phone','like', '%' .  $request->str_search . '%');
            });
        });
        $user = $query->orderBy('created_at', 'desc')->paginate(20);
       
        return view('admin.users',['user'=>$user]);
    }

    public function edituser($userid){
        $users = User::where('id',$userid)->first();
        return view('admin.edituser',['user'=>$users]);
    }
    public function detailsuser($userid){
        $users = User::where('id',$userid)->first();
        $billing = Billing::where('user_id',$userid)->latest()->take(12)->get();
        return view('admin.userdetails',['user'=>$users, 'billing'=>$billing]);
    }
    public function suspend(Request $request){
        $rules = array(
            'suspendreason'=> 'required',
            'user_id'=> 'required',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return redirect()->route('admin.customer')->withInput()->withErrors($validation);
        }
        else{
            $users = User::where('id',$request->user_id)->first();
            $input['status'] = 3;
            $input['suspend_reason'] = $request->suspendreason;
            User::where('id',$request->user_id)->update($input);
            //send email to user
            $EmailTemplate = EmailTemplate::where('key','AccountSuspended')->first();
            $subject = $EmailTemplate->subject;
            $body = $EmailTemplate->body;
            $emailKeywordsArr = config('app.email_template_var');
            for($i=0;$i<count($emailKeywordsArr);$i++){
                if($emailKeywordsArr[$i] == '[NAME]'){
                    $subject = str_replace('[NAME]',$users->name,$subject);
                    $body = str_replace('[NAME]',$users->name,$body);
                }
            }
            $subject = str_replace('[REASON]',$request->suspendreason,$subject);
            $body = str_replace('[REASON]',$request->suspendreason,$body);
            $to = $users->email;  
            $mail = new AppMail($subject,$body);
            Mail::to($to)->send($mail);
            if($users->subscription_id)
            {
                try{
                    Stripe::setApiKey(config('services.stripe.secret'));
                    $subscription = \Stripe\Subscription::retrieve($users->subscription_id);
                    $subscription->cancel();
                }catch(Exception $e){
                    Log::error('Stripe cancellation error', ['error' => $e->getMessage()]);
                }
                

                $EmailTemplate = EmailTemplate::where('key','SubscriptionCancel')->first();
                $subject = $EmailTemplate->subject;
                $body = $EmailTemplate->body;
                $emailKeywordsArr = config('app.email_template_var');
                $timestamp = time();
                for($i=0;$i<count($emailKeywordsArr);$i++){
                    if($emailKeywordsArr[$i] == '[NAME]'){
                        $subject = str_replace('[NAME]',$users->name,$subject);
                        $body = str_replace('[NAME]',$users->name,$body);
                    }
                    if($emailKeywordsArr[$i] == '[AMOUNT]'){
                        $subject = str_replace('[AMOUNT]','&pound;5.95',$subject);
                        $body = str_replace('[AMOUNT]','&pound;5.95',$body);
                    }
                    if($emailKeywordsArr[$i] == '[PLAN_NAME]'){
                        $subject = str_replace('[PLAN_NAME]','App Tax Subscription (at £5.95 / month)',$subject);
                        $body = str_replace('[PLAN_NAME]','App Tax Subscription (at £5.95 / month)',$body);
                    }
                    if($emailKeywordsArr[$i] == '[BILLING_CYCLE]'){
                        $subject = str_replace('[BILLING_CYCLE]','Monthly',$subject);
                        $body = str_replace('[BILLING_CYCLE]','Monthly',$body);
                    }
                    if($emailKeywordsArr[$i] == '[DATE]'){
                        $subject = str_replace('[DATE]',date('d-m-Y',$timestamp),$subject);
                        $body = str_replace('[DATE]',date('d-m-Y',$timestamp),$body);
                    }
                }
                $subject = str_replace('[DATEUNTILL]',date('d-m-Y',$timestamp),$subject);
                $body = str_replace('[DATEUNTILL]',date('d-m-Y',$timestamp),$body);
                $to = $users->email;  
                $mail = new AppMail($subject,$body);
                Mail::to($to)->send($mail);
            }
            //Log::info('User suspended', ['user_id' => $users->fcm_token]);
            if($users->fcm_token != ''){
                $title = "ACCOUNT_SUSPENDED";
                $body = "Hi ".$users->name.", your account has been suspended or blocked! If you think this is an error, please contact us at service@taxitax.uk.";
                $device_token = $users->fcm_token;
                $factory = (new Factory)->withServiceAccount(storage_path(config('services.googlecloud.firebase')));
                $messaging = $factory->createMessaging();

                // Create a notification message
                $message = CloudMessage::withTarget('token', $device_token)
                ->withNotification(['title'=>$title, 'body'=>$body])
                ->withData(['test' => 'testing']);
                try {
                    Log::info('Notification sent', ['user_id' => $device_token]);
                    $response = $messaging->send($message);
                } catch (\Kreait\Firebase\Exception\Messaging\FailedToSendNotification $e) {
                    Log::info('Notification error', ['error' => $e->getMessage()]);
                    echo "Error: " . $e->getMessage();
                }
            }
        }
        return redirect()->route('admin.customer')->withInput()->with('success','User suspended successfully.');
    }
    public function unsuspend(Request $request){
        $rules = array(
            'user_id'=> 'required',
        );
        $validation = Validator::make($request->all(), $rules);
        if($validation->fails()){
            return redirect()->route('admin.customer')->withInput()->withErrors($validation);
        }
        else{
            $users = User::where('id',$request->user_id)->first();
            $input['status'] = 4; // When un suspend, user need to subscribe their subscription again
            User::where('id',$request->user_id)->update($input);
            //send email to user
            $EmailTemplate = EmailTemplate::where('key','AccountUnSuspended')->first();
            $subject = $EmailTemplate->subject;
            $body = $EmailTemplate->body;
            $emailKeywordsArr = config('app.email_template_var');
            for($i=0;$i<count($emailKeywordsArr);$i++){
                if($emailKeywordsArr[$i] == '[NAME]'){
                    $subject = str_replace('[NAME]',$users->name,$subject);
                    $body = str_replace('[NAME]',$users->name,$body);
                }
            }
            $subject = str_replace('[REASON]',$request->suspendreason,$subject);
            $body = str_replace('[REASON]',$request->suspendreason,$body);
            $to = $users->email;  
            $mail = new AppMail($subject,$body);
            Mail::to($to)->send($mail);
        }
        return redirect()->route('admin.customer')->withInput()->with('success','User un suspended successfully.');
    }
    
}
