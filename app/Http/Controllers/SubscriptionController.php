<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
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
use App\Models\User;
use App\Models\EmailTemplate;
use App\Models\Billing;
use App\Models\ReferralHistory;
use App\Mail\AppMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use App\Models\Notification;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Auth as FirebaseAuth;

class SubscriptionController extends Controller
{
    public function getList($user_id){
        $billing = Billing::where('user_id',$user_id)->get();
        for($i=0; $i < count($billing); $i++){
            $billing[$i]["amount"] = number_format(($billing[$i]["amount"]/100),2,'.');
            $billing[$i]["invoice_date"] = date('d-m-Y',$billing[$i]["invoice_date"]);
            $billing[$i]["subscription_from"] = date('d-m-Y',$billing[$i]["subscription_from"]);
            $billing[$i]["subscription_to"] = date('d-m-Y',$billing[$i]["subscription_to"]);
        }
        return ['response'=>true, 'data'=>$billing];
    }

    public function stripeAuth(){
        return ['response'=>true, 'data'=>array('secret'=>config('services.stripe.secret'),'publishable_key'=>config('services.stripe.publishable_key'))];
    }

    public function createSubscription(Request $request){
        Stripe::setApiKey(config('services.stripe.secret'));
        try{
            // Create a new Stripe customer
            $customer = Customer::create([
                'email' => $request->email,
                'name'  => $request->name,
                'source' => $request->stripetoken // This is the token from the frontend
            ]);
            $paymentMethod = PaymentMethod::retrieve($request->paymentmethod);
            $paymentMethod->attach(['customer' => $customer->id]);
            $today = Carbon::now();
            $newDate = $today->addDays(3);
            $timestamp = $newDate->timestamp;
            
            $subscription = Subscription::create([
                'customer' => $customer->id,
                'items' => [
                    ['plan' => config('services.stripe.price')], // Plan ID from your Stripe account
                ],
                'expand' => ['latest_invoice.payment_intent'],
                'trial_end'=>$timestamp
            ]);
            if($subscription->id){
                $input = $request->all();
                    $input["password"] = \bcrypt($input["password"]); 
                    $input["status"] = 1; 
                    $input["role"] = 'customer'; 
                    $input["subscription_id"] = $subscription->id; 
                    $input["stripe_customer"] = $customer->id; 
                    $input["my_ref_code"] = $this->generateUniqueReferralCode();
                    $user = User::create($input);
                    $token = $user->createToken("taxiApp")->plainTextToken;
                    //send email to user
                    $EmailTemplate = EmailTemplate::where('key','WelcomeEmail')->first();
                    $subject = $EmailTemplate->subject;
                    $body = $EmailTemplate->body;
                    $emailKeywordsArr = config('app.email_template_var');
                    for($i=0;$i<count($emailKeywordsArr);$i++){
                        if($emailKeywordsArr[$i] == '[NAME]'){
                            $subject = str_replace('[NAME]',$user->name,$subject);
                            $body = str_replace('[NAME]',$user->name,$body);
                        }
                    }
                    $to = $input["email"];  
                    $mail = new AppMail($subject,$body);
                    Mail::to($to)->send($mail);

                    $EmailTemplate = EmailTemplate::where('key','SubscriptionSetup')->first();
                    $subject = $EmailTemplate->subject;
                    $body = $EmailTemplate->body;
                    $emailKeywordsArr = config('app.email_template_var');
                    for($i=0;$i<count($emailKeywordsArr);$i++){
                        if($emailKeywordsArr[$i] == '[NAME]'){
                            $subject = str_replace('[NAME]',$user->name,$subject);
                            $body = str_replace('[NAME]',$user->name,$body);
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
                    $to = $input["email"];  
                    $mail = new AppMail($subject,$body);
                    Mail::to($to)->send($mail);
                    
                    $user["name"] = $user->name;
                    $user["email"] = $user->email;
                    $user["status"] = $user->status;
                    $user["phone"] = $user->phone;
                    $user["subscription_id"] = $user->subscription_id;
            }
            return response()->json([
                'success' => true,
                'token'=>$token,
                'data'=>$user
            ]);
        }catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function generateUniqueReferralCode(){
        substr(time() . rand(1000, 9999), -6); 
        do {
            $code = substr(time() . rand(1000, 9999), -6); 
            $exists = User::where('my_ref_code',$code)->get();
        } while (count($exists)>0);
        return $code;
    }

    public function getpaymentInfo(Request $request){
        // Stripe secret key for the webhook (you can get this from the dashboard)
        $endpointSecret = config('services.stripe.webhook');
         // Get the request payload and signature
         $payload = $request->getContent();
         $sigHeader = $request->header('Stripe-Signature');
         try{
            // Verify the webhook signature to make sure the request is from Stripe
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
            // Handle the different types of events
            switch ($event->type) {
                case 'invoice.payment_succeeded':
                    $invoice = $event->data->object; // Contains a Stripe invoice object
                    
                    $user = User::where('stripe_customer',$invoice['customer'])->where('status',1)->first();
                    // Handle payment success, like marking an order as paid in your DB
                    $input["invoice_id"] = $invoice['id']; 
                    $input["amount"] = $invoice['amount_paid']; 
                    $input["invoice_date"] = $invoice['created'];
                    $input["currency"] = $invoice['currency'];
                    $input["customer_id"] = $invoice['customer'];
                    $input["email"] = $invoice['customer_email'];
                    $input["invoice_link"] = $invoice['hosted_invoice_url'];
                    $input["subscription_from"] = $invoice['period_start'];
                    $input["subscription_to"] = $invoice['period_end'];
                    $input["invoice_status"] = $invoice['status'];
                    $input["subscription_id"] = $invoice['subscription']; 
                    $input["user_id"] = $user->id; 
                    $billing = Billing::create($input);
                    /*referral coding */
                    if($user->last_subscription_date ==null && $user->ref_code !=null){
                        $refCode = $user->ref_code;
                        $user_referrer = User::where('my_ref_code',$refCode)->where('status',1)->first();
                        $refHistory['referred_id'] = $user->id;
                        $refHistory['referrer_id'] = $user_referrer->id;
                        $refHistory['redeemed'] = 'N';
                        $history = ReferralHistory::create($refHistory);
                        // fetch how many users referred and not redeemed. If that record count is 2 add one month subscription off 
                        //$getHistory_referrer = ReferralHistory::where('referrer_id',$user_referrer->id)->where('redeemed','N')->take(2)->get();
                        $getHistory_referrer = DB::table('referral_histories')
                        ->Join('users', 'users.id', '=', 'referral_histories.referred_id')
                        ->select('users.status', 'referral_histories.id', 'referral_histories.redeemed')
                        ->where('users.status',1)
                        ->where('referral_histories.redeemed','N')
                        ->where('referral_histories.referrer_id',$user_referrer->id)
                        ->take(2)
                        ->get();

                        //count total reffered 
                        $toatalHistory_referrer = DB::table('referral_histories')
                        ->select('referral_histories.id')
                        ->where('referral_histories.redeemed','Y')
                        ->where('referral_histories.referrer_id',$user_referrer->id)
                        ->get();
                        //user will avali max 12 redemption. and one redemption will involve 2 referral. therefore in the dataase there will max 24 recoreds whi has redemed Y
                        if(count($getHistory_referrer) == 2 && count($toatalHistory_referrer)<24){
                            $service = app(\App\Services\StripeRedeemSuscriptionService::class);
                            $holidayInvoice = $service->pauseTransaction($user_referrer->subscription_id);
                            for ($i = 0; $i < count($getHistory_referrer); $i++) {
                                $historyId = $getHistory_referrer[$i]->id;
                                ReferralHistory::where('id', $historyId)->update([
                                    'redeemed' => 'Y',
                                    'redeemed_date' => Carbon::now(),
                                    'redemption_details'=>$holidayInvoice
                                ]);
                            }

                            /* set notification */
                            $title = "Congratulations, ".$user_referrer->name."!";
                            $body = "You’ve been awarded a free month subscription for successfully referring two friends. Check Taxitax app for more details.";

                            $input["body"] = $body;
                            $input["title"] = $title; 
                            $input["user_id"] = $user_referrer->id; 
                            $notification = Notification::create($input);
                            $device_token = $user_referrer->fcm_token;

                            $factory = (new Factory)->withServiceAccount(storage_path(env('FIREBASE_CREDENTIALS')));
                            $messaging = $factory->createMessaging();

                            // Create a notification message
                            $message = CloudMessage::withTarget('token', $device_token)
                            ->withNotification(['title'=>$title, 'body'=>$body])
                            ->withData(['test' => 'testing']);
                            try {
                                $response = $messaging->send($message);
                            } catch (\Kreait\Firebase\Exception\Messaging\FailedToSendNotification $e) {
                                echo "Error: " . $e->getMessage();
                            }
                            /* set notification */

                            /*send email */
                            $EmailTemplate = EmailTemplate::where('key','ReferalReward')->first();
                            $subject = $EmailTemplate->subject;
                            $body = $EmailTemplate->body;
                            $emailKeywordsArr = config('app.email_template_var');
                            for($i=0;$i<count($emailKeywordsArr);$i++){
                                if($emailKeywordsArr[$i] == '[NAME]'){
                                    $subject = str_replace('[NAME]',$user_referrer->name,$subject);
                                    $body = str_replace('[NAME]',$user_referrer->name,$body);
                                }
                                if($emailKeywordsArr[$i] == '[DATE]'){
                                    $subject = str_replace('[DATE]',$holidayInvoice,$subject);
                                    $body = str_replace('[DATE]',$holidayInvoice,$body);
                                }
                            }
                            $to = $user_referrer->email;  
                            $mail = new AppMail($subject,$body);
                            Mail::to($to)->send($mail);
                            /*send email */

                        }
                    }
                    /*referral coding */
                    User::where('id',$user->id)->update(['last_subscription_date'=>Carbon::now()]);
                    //send email to user
                    $EmailTemplate = EmailTemplate::where('key','PaymentSuccess')->first();
                    $subject = $EmailTemplate->subject;
                    $body = $EmailTemplate->body;
                    $emailKeywordsArr = config('app.email_template_var');
                    for($i=0;$i<count($emailKeywordsArr);$i++){
                        if($emailKeywordsArr[$i] == '[NAME]'){
                            $subject = str_replace('[NAME]',$user->name,$subject);
                            $body = str_replace('[NAME]',$user->name,$body);
                        }
                        if($emailKeywordsArr[$i] == '[AMOUNT]'){
                            $subject = str_replace('[AMOUNT]','&pound;'.$invoice['amount_paid']/100,$subject);
                            $body = str_replace('[AMOUNT]','&pound;'.$invoice['amount_paid']/100,$body);
                        }
                        if($emailKeywordsArr[$i] == '[PLAN_NAME]'){
                            $subject = str_replace('[PLAN_NAME]','App Tax Subscription (at £5.95 / month)',$subject);
                            $body = str_replace('[PLAN_NAME]','App Tax Subscription (at £5.95 / month)',$body);
                        }
                        if($emailKeywordsArr[$i] == '[BILLING_CYCLE]'){
                            $subject = str_replace('[BILLING_CYCLE]','Monthly',$subject);
                            $body = str_replace('[BILLING_CYCLE]','Monthly',$body);
                        }
                        if($emailKeywordsArr[$i] == '[TRANSACTION_ID]'){
                            $subject = str_replace('[TRANSACTION_ID]',$invoice['id'],$subject);
                            $body = str_replace('[TRANSACTION_ID]',$invoice['id'],$body);
                        }
                        if($emailKeywordsArr[$i] == '[DATE]'){
                            $subject = str_replace('[DATE]',date('d-m-Y',$invoice['created']),$subject);
                            $body = str_replace('[DATE]',date('d-m-Y',$invoice['created']),$body);
                        }
                    }
                    $to = $input["email"];  
                    $mail = new AppMail($subject,$body);
                    Mail::to($to)->send($mail);
                    break;
                case 'invoice.payment_failed':
                    $invoice = $event->data->object; // Contains a Stripe invoice object
                    // Handle payment failure, maybe send a notification or retry the payment
                    //Log::info('Invoice Payment Failed:', ['invoice' => $invoice]);
                    $user = User::where('stripe_customer',$invoice['customer'])->where('status',1)->first();
                    // Handle payment failed, like marking an order as paid in your DB
                    $input["invoice_id"] = $invoice['id']; 
                    $input["amount"] = $invoice['amount_paid']; 
                    $input["invoice_date"] = $invoice['created'];
                    $input["currency"] = $invoice['currency'];
                    $input["customer_id"] = $invoice['customer'];
                    $input["email"] = $invoice['customer_email'];
                    $input["invoice_link"] = $invoice['invoice_pdf'];
                    $input["subscription_from"] = $invoice['period_start'];
                    $input["subscription_to"] = $invoice['period_end'];
                    $input["invoice_status"] = $invoice['status'];
                    $input["subscription_id"] = $invoice['subscription']; 
                    $input["user_id"] = $user->id;  
                    $user = Billing::create($input);
                    //send email to user
                    $EmailTemplate = EmailTemplate::where('key','PaymentFailed')->first();
                    $subject = $EmailTemplate->subject;
                    $body = $EmailTemplate->body;
                    $emailKeywordsArr = config('app.email_template_var');
                    for($i=0;$i<count($emailKeywordsArr);$i++){
                        if($emailKeywordsArr[$i] == '[NAME]'){
                            $subject = str_replace('[NAME]',$user->name,$subject);
                            $body = str_replace('[NAME]',$user->name,$body);
                        }
                        if($emailKeywordsArr[$i] == '[AMOUNT]'){
                            $subject = str_replace('[AMOUNT]','&pound;'.$invoice['amount_paid']/100,$subject);
                            $body = str_replace('[AMOUNT]','&pound;'.$invoice['amount_paid']/100,$body);
                        }
                        if($emailKeywordsArr[$i] == '[PLAN_NAME]'){
                            $subject = str_replace('[PLAN_NAME]','App Tax Subscription (at £5.95 / month)',$subject);
                            $body = str_replace('[PLAN_NAME]','App Tax Subscription (at £5.95 / month)',$body);
                        }
                        if($emailKeywordsArr[$i] == '[BILLING_CYCLE]'){
                            $subject = str_replace('[BILLING_CYCLE]','Monthly',$subject);
                            $body = str_replace('[BILLING_CYCLE]','Monthly',$body);
                        }
                        if($emailKeywordsArr[$i] == '[TRANSACTION_ID]'){
                            $subject = str_replace('[TRANSACTION_ID]',$invoice['id'],$subject);
                            $body = str_replace('[TRANSACTION_ID]',$invoice['id'],$body);
                        }
                        if($emailKeywordsArr[$i] == '[DATE]'){
                            $subject = str_replace('[DATE]',date('d-m-Y',$invoice['created']),$subject);
                            $body = str_replace('[DATE]',date('d-m-Y',$invoice['created']),$body);
                        }
                    }
                    $to = $input["email"];  
                    $mail = new AppMail($subject,$body);
                    Mail::to($to)->send($mail);
                    break;
                default:
                    // Handle other events
                    Log::info('Unhandled event type:', ['event' => $event]);
                    break;
            }
            return response()->json(['status' => 'success']);

         }catch (\UnexpectedValueException $e) {
            // Invalid payload
            Log::error('Invalid payload:', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'fail'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            Log::error('Invalid signature:', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'fail'], 400);
        }
    }

    public function removeSubscription(Request $request){
            $input = $request->all();

            Stripe::setApiKey(config('services.stripe.secret'));
            $subscription = \Stripe\Subscription::retrieve($input['subscription_id']);
            $subscription->cancel();
            $updateinput['suspend_reason'] = $input['reason'].'###'.$input['feedback'];
            $updateinput['status'] = 0;
            $timestamp = time();
            User::where('id',$input['user_id'])->update($updateinput);
    
            $user = User::where('id',$input['user_id'])->first();
            $EmailTemplate = EmailTemplate::where('key','SubscriptionCancel')->first();
            $subject = $EmailTemplate->subject;
            $body = $EmailTemplate->body;
            $emailKeywordsArr = config('app.email_template_var');
            for($i=0;$i<count($emailKeywordsArr);$i++){
                if($emailKeywordsArr[$i] == '[NAME]'){
                    $subject = str_replace('[NAME]',$user->name,$subject);
                    $body = str_replace('[NAME]',$user->name,$body);
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
                if($emailKeywordsArr[$i] == '[DATEUNTILL]'){
                    $subject = str_replace('[DATEUNTILL]',date('d-m-Y',$timestamp),$subject);
                    $body = str_replace('[DATEUNTILL]',date('d-m-Y',$timestamp),$body);
                }
            }
            $to = $user->email;  
            $mail = new AppMail($subject,$body);
            Mail::to($to)->send($mail);
            return response()->json([
                'success' => true,
                'msg'=>'User removed successfully'
            ]);

    }
    public function getTrialEndDate(){
        $today = Carbon::now();
        $newDate = $today->addDays(3);
        return response()->json(['status' => 'success','trial_end_date'=>$newDate->format('d-m-Y')]);
    }

    public function renewsuscription(Request $request){
        $input = $request->all();
        Stripe::setApiKey(config('services.stripe.secret'));
        $user = User::find($input['user_id']);
        $customerId = $user->stripe_customer;
        $paymentMethod = PaymentMethod::retrieve($request->paymentmethod);
        $paymentMethod->attach(['customer' => $customerId]);
        $subscription = Subscription::create([
                'customer' => $customerId,
                'items' => [
                    ['plan' => config('services.stripe.price')], // Plan ID from your Stripe account
                ],
                'expand' => ['latest_invoice.payment_intent']
        ]);
        $updateinput['status'] = 1;
        $updateinput['subscription_id'] = $subscription->id;
        User::where('id',$input['user_id'])->update($updateinput);
        
        return response()->json([
            'success' => true,
            'msg'=>'Subscription renewed successfully'
        ]);
    }

    public function renewsuscriptionemail(Request $request){
        $input = $request->all();
        $user = User::where('id',$input['user_id'])->get();
        $EmailTemplate = EmailTemplate::where('key','ResubscriptionEmail')->first();
        $subject = $EmailTemplate->subject;
        $body = $EmailTemplate->body;
        $emailKeywordsArr = config('app.email_template_var');
        for($i=0;$i<count($emailKeywordsArr);$i++){
            if($emailKeywordsArr[$i] == '[NAME]'){
                $subject = str_replace('[NAME]',$user->name,$subject);
                $body = str_replace('[NAME]',$user->name,$body);
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
        $to = $user->email;
        $mail = new AppMail($subject,$body);
        Mail::to($to)->send($mail);

    }

    public function teststripe(){
        $invoice['customer'] = 'cus_SyRLophOXvOkvb';
        $invoice['subscription'] = 'sub_1S2UT5ABT9Txt98Dyet2UIz5';
        $user = User::where('stripe_customer',$invoice['customer'])->where('status',1)->first();
        
        // Handle payment success, like marking an order as paid in your DB
        // $input["invoice_id"] = $invoice['id']; 
        // $input["amount"] = $invoice['amount_paid']; 
        // $input["invoice_date"] = $invoice['created'];
        // $input["currency"] = $invoice['currency'];
        // $input["customer_id"] = $invoice['customer'];
        // $input["email"] = $invoice['customer_email'];
        // $input["invoice_link"] = $invoice['hosted_invoice_url'];
        // $input["subscription_from"] = $invoice['period_start'];
        // $input["subscription_to"] = $invoice['period_end'];
        // $input["invoice_status"] = $invoice['status'];
        // $input["subscription_id"] = $invoice['subscription']; 
        // $input["user_id"] = $user->id; 
        // $billing = Billing::create($input);
        // /*referral coding */
        if($user->last_subscription_date ==null && $user->ref_code !=null){
            $refCode = $user->ref_code;
            $user_referrer = User::where('my_ref_code',$refCode)->where('status',1)->first();
            $refHistory['referred_id'] = $user->id;
            $refHistory['referrer_id'] = $user_referrer->id;
            $refHistory['redeemed'] = 'N';
            $history = ReferralHistory::create($refHistory);
            // fetch how many users referred and not redeemed. If that record count is 2 add one month subscription off 
            //$getHistory_referrer = ReferralHistory::where('referrer_id',$user_referrer->id)->where('redeemed','N')->take(2)->get();
            $getHistory_referrer = DB::table('referral_histories')
            ->Join('users', 'users.id', '=', 'referral_histories.referred_id')
            ->select('users.status', 'referral_histories.id', 'referral_histories.redeemed')
            ->where('users.status',1)
            ->where('referral_histories.redeemed','N')
            ->where('referral_histories.referrer_id',$user_referrer->id)
            ->take(2)
            ->get();
            if(count($getHistory_referrer) == 2){
                $service = app(\App\Services\StripeRedeemSuscriptionService::class);
                $holidayInvoice = $service->pauseTransaction($user_referrer->subscription_id);
                for ($i = 0; $i < count($getHistory_referrer); $i++) {
                    $historyId = $getHistory_referrer[$i]->id;
                    ReferralHistory::where('id', $historyId)->update([
                        'redeemed' => 'Y',
                        'redeemed_date' => Carbon::now(),
                        'redemption_details'=>$holidayInvoice
                    ]);
                }
            }
        }
        /*referral coding */
        User::where('id',$user->id)->update(['last_subscription_date'=>Carbon::now()]);
    }
}