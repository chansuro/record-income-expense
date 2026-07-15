<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use App\Services\TwilioService;
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
use Illuminate\Support\Facades\Log;

class signupController extends Controller
{
    public function __construct(TwilioService $twilioService)
    {
        $this->twilioService = $twilioService;
    }

    public function index(){
        return \view('web.signup');
    }

    public function signup(Request $request){
        // Validate the incoming request data
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8'
        ]);
        if($request->referral_code != null || $request->referral_code != ''){
            $userRefCode = User::where('my_ref_code',$request->referral_code)->first();
            if(!$userRefCode){
                throw ValidationException::withMessages([
                    'referral_code' => 'NOREFCODE',
                ]);
            }
        }
        $userEmail = User::where('email',$request->email)->first();
        if($userEmail){
            throw ValidationException::withMessages([
            'email' => 'Email already exists. Please use another email!',
            ]);
        }else{
            $userPhone = User::where('phone',$request->phone)->first();
            if($userPhone){
                throw ValidationException::withMessages([
                    'phone' => 'Phone number already exists. Please use another phone number!',
                ]);
            }else{
                $sessionId = session()->getId();
                $otp = $this->getotp($request->phone,$request->email,$request->name);
                // Store signup data in cache for later processing
                Cache::put('signup_user_'.$sessionId, ['name' => $request->name, 'email' => $request->email, 'password' => $request->password, 'phone' => $request->phone,'referral_code'=>$request->referral_code,'otp'=>$otp], now()->addMinutes(180));
            }
        }
        return redirect()->route('general.validateotp');
    }
    public function validateotp(){
        $sessionId = session()->getId();
        $value = Cache::get('signup_user_'.$sessionId);
        if (!$value && !isset($value['otp'])) {
            return redirect()->route('general.signup');
        }
        return \view('web.validateotp',$value);
    }
    public function resendotp(){
        $sessionId = session()->getId();
        $value = Cache::get('signup_user_'.$sessionId);
        $phone = $value['phone'];
        $email = $value['email'];
        $name = $value['name'];
        
        $otp = $this->getotp($phone,$email,$name);
        $value['otp'] = $otp;
        Cache::put('signup_user_'.$sessionId, $value, now()->addMinutes(180));
        return response()->json([
        'message' => 'OTP resent successfully'
        ]);
    }
    protected function getotp($recipient_phone_numbers,$repipient_emai,$recipient_name){
        
        $randomNumber = rand(1012, 9001);
        $message = "Your one-time password (OTP) for taxitax is: {$randomNumber}";
        //$sessionId = session()->getId();
        //$value = Cache::get('signup_user_'.$sessionId);
        $today = Carbon::now();
        $newDate = $today->addDays(3);
        $timestamp = $newDate->timestamp;
            
        if(isset($recipient_phone_numbers) && trim(strlen($recipient_phone_numbers)>0)){
            $this->twilioService->sendSms('+44'.$recipient_phone_numbers, $message);
        }else{
            $EmailTemplate = EmailTemplate::where('key','SignupVerifyEmail')->first();
            $subject = $EmailTemplate->subject;
            $body = $EmailTemplate->body;
            $emailKeywordsArr = config('app.email_template_var');
            for($i=0;$i<count($emailKeywordsArr);$i++){
                if($emailKeywordsArr[$i] == '[NAME]'){
                    $subject = str_replace('[NAME]',$recipient_name,$subject);
                    $body = str_replace('[NAME]',$recipient_name,$body);
                }
                if($emailKeywordsArr[$i] == '[OTP_CODE]'){
                    $subject = str_replace('[OTP_CODE]',$randomNumber,$subject);
                    $body = str_replace('[OTP_CODE]',$randomNumber,$body);
                }
                if($emailKeywordsArr[$i] == '[DATE]'){
                    $subject = str_replace('[DATE]',date('d-m-Y',$timestamp),$subject);
                    $body = str_replace('[DATE]',date('d-m-Y',$timestamp),$body);
                }
            }
            $to = $repipient_emai;  
            $mail = new AppMail($subject,$body);
            Mail::to($to)->send($mail);
        }
        
        return $randomNumber;
    }

    public function postvalidateotp(Request $request){
        $request->validate([
            'otp1' => 'required|digits:1',
            'otp2' => 'required|digits:1',
            'otp3' => 'required|digits:1',
            'otp4' => 'required|digits:1',
        ]);
        $sessionId = session()->getId();
        $value = Cache::get('signup_user_'.$sessionId);
        if (!$value || !isset($value['otp'])) {
            return redirect()->route('general.signup');
        }
        $otppost = $request->otp1 . $request->otp2 . $request->otp3 . $request->otp4;
        if ($otppost == $value['otp']) {
            return redirect()->route('general.subscribe');
        } else {
            throw ValidationException::withMessages([
                'otp' => 'The provided OTP is incorrect.',
            ]);
        }
    }

    public function subscribe(){
        $sessionId = session()->getId();
        $value = Cache::get('signup_user_'.$sessionId);
        // if (!$value || !isset($value['otp'])) {
        //     return redirect()->route('general.signup');
        // }
        //Log::info('Accessing subscribe page', ['session_id' => $value]);
        $today = Carbon::now();
        $value['newDate'] = $today->addDays(3)->format('d-m-Y');
        $value['dayofeverysubscription'] = Carbon::parse($value['newDate'])->format('jS');
        return \view('web.subscribe',$value);
    }
    public function subscribestripe(Request $request){
        Stripe::setApiKey(config('services.stripe.secret'));
        $sessionId = session()->getId();
        $value = Cache::get('signup_user_'.$sessionId);
        if (!$value || !isset($value['otp'])) {
            return redirect()->route('general.signup'); 
        }
        try {

            // $customer = Customer::create([
            //     'email' => $value['email'],
            //     'source'  => $request->stripeToken,
            // ]);

            // // Attach payment method
            // $paymentMethod = PaymentMethod::retrieve($request->payment_method);
            // $paymentMethod->attach(['customer' => $customer->id]);  

            // Customer::update($customer->id, [
            //     'invoice_settings' => ['default_payment_method' => $request->payment_method],
            // ]);

            $today = Carbon::now();
            $newDate = $today->addDays(3);
            $timestamp = $newDate->timestamp;

            // $subscription = Subscription::create([
            //     'customer' => $customer->id,
            //     'items' => [['plan' => $request->plan]],
            //     'expand' => ['latest_invoice.payment_intent'],
            //     'trial_end'=>$timestamp
            // ]);
            
            //if($subscription->id){
                $input['email'] = $value['email'];
                $input['name'] = $value['name'];
                $input['phone'] = $value['phone'];
                if($value['referral_code'] != ''){
                    $refUser = User::where('my_ref_code',$value['referral_code'])->first();
                    if($refUser){
                        $input['ref_code'] = $value['referral_code'];
                    }
                }
                $input["password"] = \bcrypt($value["password"]); 
                $input["status"] = 1; 
                $input["role"] = 'customer'; 
                $input["subscription_id"] = 'ttt'; 
                $input["stripe_customer"] = 'hhh'; 
                $input["platform"] = 'web';
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
                        $subject = str_replace('[AMOUNT]','&pound;6.99',$subject);
                        $body = str_replace('[AMOUNT]','&pound;6.99',$body);
                    }
                    if($emailKeywordsArr[$i] == '[PLAN_NAME]'){
                        $subject = str_replace('[PLAN_NAME]','App Tax Subscription (at £6.99 / month)',$subject);
                        $body = str_replace('[PLAN_NAME]','App Tax Subscription (at £6.99 / month)',$body);
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
                
                // New user signup email to admin
                $subject = "New User Signup: ".$user->name;
                $body = "A new user has signed up for the App Tax Subscription. Here are the details:\n\nName: ".$user->name."\nEmail: ".$user->email."\nPhone: ".$user->phone."\nSubscription Type: App Tax Subscription (at £6.99 / month)\nSignup Date: ".date('d-m-Y', $timestamp)."\n\nPlease check the admin panel for more details.";
                $to = config('app.admin_email');
                $mail = new AppMail($subject,$body);
                Mail::to($to)->send($mail);

                /* set notification */
                $title = "Welcome to TaxiTax!";
                $body = "Hi ".$user->name.", Welcome to Taxitax! We’re thrilled to have you on board. Managing your taxi book-keeping & taxes has never been easier. With Taxitax, you can effortlessly track your earnings and expenses, save time with automated calculations and Stay on top of tax deadlines.
                If you have any questions, our support team is here to help—just reach out at service@taxitax.uk. Thank you for choosing Taxitax. We’re here to make your tax worry stress-free! Happy driving, Taxitax.uk";

                $input["body"] = $body;
                $input["title"] = $title; 
                $input["user_id"] = $user->id; 
                $notification = Notification::create($input);
                // $device_token = $user->fcm_token;

                // $factory = (new Factory)->withServiceAccount(storage_path(config('services.googlecloud.firebase')));
                // $messaging = $factory->createMessaging();

                // // Create a notification message
                // $message = CloudMessage::withTarget('token', $device_token)
                // ->withNotification(['title'=>$title, 'body'=>$body])
                // ->withData(['test' => 'testing']);
                // try {
                //     $response = $messaging->send($message);
                // } catch (\Kreait\Firebase\Exception\Messaging\FailedToSendNotification $e) {
                //     echo "Error: " . $e->getMessage();
                // }
                /* set notification */
                
                $user["name"] = $user->name;
                $user["email"] = $user->email;
                $user["status"] = $user->status;
                $user["phone"] = $user->phone;
                $user["subscription_id"] = $user->subscription_id;
            //}
            Cache::forget('signup_user_'.$sessionId);
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

    }
    public function signupconfirmation(){
        return \view('web.signupconfirmation',[]);
    }
    public function generateUniqueReferralCode(){
        substr(time() . rand(1000, 9999), -6); 
        do {
            $code = substr(time() . rand(1000, 9999), -6); 
            $exists = User::where('my_ref_code',$code)->get();
        } while (count($exists)>0);
        return $code;
    }
}
