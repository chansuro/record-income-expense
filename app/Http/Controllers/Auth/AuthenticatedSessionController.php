<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Models\User;
use App\Models\EmailTemplate;
use App\Mail\AppMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        Cache::forget('logged_in_user_otp');
        Cache::forget('logged_in_user');
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        //$request->authenticate();

        //$request->session()->regenerate();
        //return redirect()->intended(route('dashboard', absolute: false));
        $user = User::where('email',$request->email)->first();
        if($user){
            $to = $user->email;  
            $randomNumber = rand(100012, 900001);
            Cache::put('logged_in_user_otp', $randomNumber, now()->addMinutes(10));
            Cache::put('logged_in_user',$user, now()->addMinutes(10));
            $EmailTemplate = EmailTemplate::where('key','WebLoginOTPEmail')->first();
            $subject = $EmailTemplate->subject;
            $body = $EmailTemplate->body;
            $emailKeywordsArr = config('app.email_template_var');
            for($i=0;$i<count($emailKeywordsArr);$i++){
                if($emailKeywordsArr[$i] == '[OTP_CODE]'){
                    $subject = str_replace('[OTP_CODE]',$randomNumber,$subject);
                    $body = str_replace('[OTP_CODE]',$randomNumber,$body);
                }
            }
            $to =  $user->email;  
            $mail = new AppMail($subject,$body);
            Mail::to($to)->send($mail);
            //$body = "Your one-time password (OTP) for taxitax is: {$randomNumber}";
            //$mail = new AppMail('Login OTP code',$body);
            //Mail::to($to)->send($mail);
            //Cache::forget('logged_in_user_otp');
            return redirect()->intended(route('emailotp', absolute: false));
        }else{
            return redirect()->back()
            ->withErrors([
                'email' => 'We are unable to find this user with this email address.'
            ])
            ->withInput();
            //return redirect()->route('login')->withInput()->with('email','We are unable to find this user with this email address.');
        }
    }

    public function emailotp(Request $request)
    {
        if (Cache::has('logged_in_user_otp')) {
            // Cache exists
        }else{
            return redirect()->back()
            ->withErrors([
                'email' => 'You are not authorised to access this page.'
            ])
            ->withInput();
            //return redirect()->route('login')->withInput()->with('error','You are not authorised to access this page.');
        }
        return view('auth.otp');
    }
    public function emailotppost(Request $request): RedirectResponse
    {
        $otp = Cache::get('logged_in_user_otp');
        $cacheUser = Cache::get('logged_in_user');
        if($otp == $request->otp){
            if(!$cacheUser->id){
                return redirect()->route('login')
                ->withErrors([
                    'email' => 'OTP mismatch. Please provide a valid OTP.'
                ])
                ->withInput();
            }
            Auth::guard('user')->login($cacheUser);
            $request->session()->regenerate();
            Cache::forget('logged_in_user_otp');
            Cache::forget('logged_in_user');
            return redirect()->route('user.dashboard');

        }else{
            return redirect()->back()
            ->withErrors([
                'otp' => 'OTP mismatch. Please provide a valid OTP.'
            ])
            ->withInput();
            return redirect()->route('login')->withInput()->with('error','OTP mismatch. Please provide a valid OTP.');
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('user')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
