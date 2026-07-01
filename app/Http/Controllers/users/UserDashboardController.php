<?php

namespace App\Http\Controllers\users;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Billing;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\Plan;
use Stripe\Webhook;
use Stripe\Invoice;
use Stripe\Subscription;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Checkout\Session as StripeSession;
use Carbon\Carbon;
use App\Models\EmailTemplate;
use App\Mail\AppMail;
use Illuminate\Support\Facades\Mail;
use App\Models\Notification;

use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Auth as FirebaseAuth;

use Illuminate\Validation\ValidationException;

class UserDashboardController extends Controller
{
    //
    public function index()
    {
        if (Auth::guard('user')->check()) {
            $user = Auth::guard('user')->user();
            $billing = Billing::where('user_id',$user->id)->orderBy('subscription_to', 'desc')->first();
            return view('user.dashboard', compact('user','billing'));
        } else {
            return redirect()->route('login');
        }
    }

    public function subscriptions(){
        if (Auth::guard('user')->check()) {
            $user = Auth::guard('user')->user();
            $billing = Billing::where('user_id',$user->id)->orderBy('subscription_to', 'desc')->get();
            return view('user.subscriptions', compact('user','billing'));
        } else {
            return redirect()->route('login');
        }
    }

    public function subscribeuser(){
        if (Auth::guard('user')->check()) {
            return view('user.subscribe');
        } else {
            return redirect()->route('login');
        }
    }
    public function resubscribeconfirmation(){
        if (Auth::guard('user')->check()) {
            return view('user.resubscribeconfirmation');
        } else {
            return redirect()->route('login');
        }
    }

    public function resubscribestripe(Request $request){
        if (Auth::guard('user')->check()) {
            Stripe::setApiKey(config('services.stripe.secret'));
            $user = Auth::guard('user')->user();
            try {
                $customerId = $user->stripe_customer;
                // Attach payment method
                $paymentMethod = PaymentMethod::retrieve($request->payment_method);
                $paymentMethod->attach(['customer' => $user->stripe_customer]);  
    
                // Customer::update($user->stripe_customer, [
                //     'invoice_settings' => ['default_payment_method' => $request->payment_method],
                // ]);
                $subscription = Subscription::create([
                    'customer' => $customerId,
                    'items' => [['plan' => $request->plan]],
                    'expand' => ['latest_invoice.payment_intent']
                ]);
                
                if($subscription->id){
                    $user->update([
                        'status'=>1,
                        'subscription_id'=>$subscription->id
                    ]);
                    $timestamp = time();
                    //send email to user
                    $EmailTemplate = EmailTemplate::where('key','ResubscribeSuccessfulEmail')->first();
                    $subject = $EmailTemplate->subject;
                    $body = $EmailTemplate->body;
                    $emailKeywordsArr = config('app.email_template_var');
                    for($i=0;$i<count($emailKeywordsArr);$i++){
                        if($emailKeywordsArr[$i] == '[NAME]'){
                            $subject = str_replace('[NAME]',$user->name,$subject);
                            $body = str_replace('[NAME]',$user->name,$body);
                        }
                        if($emailKeywordsArr[$i] == '[PLAN_NAME]'){
                            $subject = str_replace('[PLAN_NAME]','App Tax Subscription (at £6.99 / month)',$subject);
                            $body = str_replace('[PLAN_NAME]','App Tax Subscription (at £6.99 / month)',$body);
                        }
                        if($emailKeywordsArr[$i] == '[DATE]'){
                            $subject = str_replace('[DATE]',date('d-m-Y',$timestamp),$subject);
                            $body = str_replace('[DATE]',date('d-m-Y',$timestamp),$body);
                        }
                        if($emailKeywordsArr[$i] == '[NEXT_BILLING_DATE]'){
                            $timestampnextmonth = strtotime('+30 days');
                            $subject = str_replace('[NEXT_BILLING_DATE]',date('d-m-Y',$timestampnextmonth),$subject);
                            $body = str_replace('[NEXT_BILLING_DATE]',date('d-m-Y',$timestampnextmonth),$body);
                        }
                    }
                    $to =  $user->email;  
                    $mail = new AppMail($subject,$body);
                    Mail::to($to)->send($mail);
    
                    /* set notification */
                    $title = "Congratulations, ".$user->name."!";
                    $body = "Your subscription has been successfully renewed, and your account remains active with full access to all TaxiTax features.";
    
                    $input["body"] = $body;
                    $input["title"] = $title; 
                    $input["user_id"] = $user->id; 
                    $notification = Notification::create($input);
                    // $device_token = $user->fcm_token;
    
                    if($user->fcm_token != ''){
                        $title = "Congratulations, ".$user->name."!";
                        $body = "Hi ".$user->name.", your subscription has been successfully renewed, and your account remains active with full access to all TaxiTax features.";
                        $device_token = $user->fcm_token;
                        $factory = (new Factory)->withServiceAccount(storage_path(config('services.googlecloud.firebase')));
                        $messaging = $factory->createMessaging();
    
                        // Create a notification message
                        $message = CloudMessage::withTarget('token', $device_token)
                        ->withNotification(['title'=>$title, 'body'=>$body])
                        ->withData(['test' => 'testing']);
                        try {
                            //Log::info('Notification sent', ['user_id' => $device_token]);
                            $response = $messaging->send($message);
                        } catch (\Kreait\Firebase\Exception\Messaging\FailedToSendNotification $e) {
                            //Log::info('Notification error', ['error' => $e->getMessage()]);
                            echo "Error: " . $e->getMessage();
                        }
                    }
                }
                return response()->json([
                    'success' => true,
                    'token'=>$token,
                    'data'=>$user
                ]);
            } catch (\Exception $e) {
                throw ValidationException::withMessages([
                    'payment' => 'There was an error processing your payment: ' . $e->getMessage(),
                ]); 
            }
        } else {
            return redirect()->route('login');
        }
    }

}
