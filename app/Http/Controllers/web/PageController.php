<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\EmailTemplate;
use App\Mail\AppMail;
use Illuminate\Support\Facades\Mail;    

class PageController extends Controller
{
    public function contactUs(Request $request){
        $rules = $request->validate([
            'name' => 'required',
            'email' => 'required',
            'phone' => 'required',
        ]);
        $client = new \GuzzleHttp\Client();
        $response = $client->post('https://www.google.com/recaptcha/api/siteverify', [
            'form_params' => [
                'secret' => config('app.google_captcha_secret_key'),
                'response' => $request->recaptcha_token
            ]
        ]);

        $result = json_decode($response->getBody(), true);
        if (!($result['success'] ?? false) || ($result['score'] < 0.5)) {
            return back()->withErrors(['captcha' => 'reCAPTCHA validation failed']);
        }
        // $validation = Validator::make($request->all(), $rules);
        // if($validation->fails()){
        //     return redirect()->route('general.contact')->withInput()->withErrors($validation);
        // }else{
            $EmailTemplate = EmailTemplate::where('key','contactusEmail')->first();
            $subject = $EmailTemplate->subject;
            $body = $EmailTemplate->body;
            $emailKeywordsArr = config('app.email_template_var');
            for($i=0;$i<count($emailKeywordsArr);$i++){
                if($emailKeywordsArr[$i] == '[NAME]'){
                    $subject = str_replace('[NAME]',$request->name,$subject);
                    $body = str_replace('[NAME]',$request->name,$body);
                }
                if($emailKeywordsArr[$i] == '[EMAIL]'){
                    $subject = str_replace('[EMAIL]',$request->email,$subject);
                    $body = str_replace('[EMAIL]',$request->email,$body);
                }
                if($emailKeywordsArr[$i] == '[PHONE]'){
                    $subject = str_replace('[PHONE]',$request->phone,$subject);
                    $body = str_replace('[PHONE]',$request->phone,$body);
                }
                if($emailKeywordsArr[$i] == '[MESSAGE]'){
                    $subject = str_replace('[MESSAGE]',$request->message,$subject);
                    $body = str_replace('[MESSAGE]',$request->message,$body);
                }
            }
            $to = config('mail.from.address');
            $mail = new AppMail($subject,$body);
            Mail::to($to)->send($mail);
            return redirect()->back()->with('success', 'We have received your message. We will contact you as soon as possible.');
        //}
    }

}
