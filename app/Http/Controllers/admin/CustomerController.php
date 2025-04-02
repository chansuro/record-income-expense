<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\EmailTemplate;
use App\Mail\AppMail;
use Illuminate\Support\Facades\Mail;
use Stripe\Stripe;
use Stripe\Subscription;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    //
    public function index(Request $request){
        $query = User::where('role','customer')->where('status','1');
        $query->when($request->has('start_date') && $request->input('end_date'), function ($query) use ($request) {
            $query->whereBetween('created_at', [$request->start_date.' 00:00:00', $request->end_date.' 23:59:59']);
        });
        $query->when($request->has('start_date'), function ($query) use ($request) {
            $query->where('created_at', '>=', $request->start_date.' 00:00:00');
        });
        $query->when($request->has('to_date'), function ($query) use ($request) {
            $query->where('created_at', '<=', $request->to_date.' 23:59:59');
        });
        $query->when(isset($request->str_search), function ($query) use ($request) {
            $query->where(function ($query) use ($request) {
                $query->where('name','like', '%' .  $request->str_search . '%');
                $query->orWhere('email','like', '%' .  $request->str_search . '%');
                $query->orWhere('phone','like', '%' .  $request->str_search . '%');
            });
        });
        $user = $query->paginate(20);
        return view('admin.users',['user'=>$user]);
    }
    public function upcomingsubscription(Request $request){
        $query = User::where('role','customer')->where('status','1');
        $query->when($request->has('start_date') && $request->input('end_date'), function ($query) use ($request) {
            $query->whereBetween('created_at', [$request->start_date.' 00:00:00', $request->end_date.' 23:59:59']);
        });
        $query->when($request->has('start_date'), function ($query) use ($request) {
            $query->where('created_at', '>=', $request->start_date.' 00:00:00');
        });
        $query->when($request->has('to_date'), function ($query) use ($request) {
            $query->where('created_at', '<=', $request->to_date.' 23:59:59');
        });
        $query->when(isset($request->str_search), function ($query) use ($request) {
            $query->where(function ($query) use ($request) {
                $query->where('name','like', '%' .  $request->str_search . '%');
                $query->orWhere('email','like', '%' .  $request->str_search . '%');
                $query->orWhere('phone','like', '%' .  $request->str_search . '%');
            });
        });
        $user = $query->paginate(20);
        return view('admin.users',['user'=>$user]);
    }

    public function edituser($userid){
        $users = User::where('id',$userid)->first();
        return view('admin.edituser',['user'=>$users]);
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
                Stripe::setApiKey(\env('STRIPE_SECRET'));
                $subscription = \Stripe\Subscription::retrieve($users->subscription_id);
                $subscription->cancel();

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
                        $subject = str_replace('[AMOUNT]','&pound;10',$subject);
                        $body = str_replace('[AMOUNT]','&pound;10',$body);
                    }
                    if($emailKeywordsArr[$i] == '[PLAN_NAME]'){
                        $subject = str_replace('[PLAN_NAME]','App Tax Subscription (at £10.00 / month)',$subject);
                        $body = str_replace('[PLAN_NAME]','App Tax Subscription (at £10.00 / month)',$body);
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
        }
        return redirect()->route('admin.customer')->withInput()->with('success','User suspended successfully.');
    }
    
}
