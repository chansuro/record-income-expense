<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Auth as FirebaseAuth;
use App\Models\Notification;

class NotificationController extends Controller
{
    public function sendPushNotification(Request $request)
    {

        
        // $factory = (new Factory)->withServiceAccount(env('FIREBASE_CREDENTIALS'));
        // $auth = $factory->createAuth();
        // try {
        //     $verifiedIdToken = $auth->verifyIdToken($request->input('device_token'));
        //     echo 'Token is valid!';
        // } catch (FailedToVerifyToken $e) {
        //     echo 'Invalid token: ' . $e->getMessage();
        // }
        //Initialize Firebase
        $factory = (new Factory)->withServiceAccount(config('services.googlecloud.firebase'));
        $messaging = $factory->createMessaging();

        // Create a notification message
        $message = CloudMessage::withTarget('token', $request->input('device_token'))
        ->withNotification(['title'=>$request->input('title'), 'body'=>$request->input('body')])
        ->withData(['test' => '123']);

        try {
            $response = $messaging->send($message);
            echo "Notification sent!";
        } catch (\Kreait\Firebase\Exception\Messaging\FailedToSendNotification $e) {
            echo "Error: " . $e->getMessage();
        }

        // $deviceToken = $request->input('device_token');
        // $title = $request->input('title');
        // $body = $request->input('body');

        // $response = $this->firebaseService->sendNotification($deviceToken, $title, $body);

        // return response()->json(['message' => $response]);
    }

    public function getNotifications($user_id){
        $notifications = Notification::where('user_id',$user_id)->orderBy('created_at', 'desc')->limit(10)->get();
        return ['response'=>true, 'data'=>$notifications];

    }

    public function updateNotification(Request $request){
        $input = $request->all();
        $input['readstatus'] = 'Y';
        $millage = Notification::where('id',$input['id'])->where('user_id',$input['user_id'])->update($input);
        return ['response'=>true, 'msg'=>'Notification edited successfully!'];
    }
    public function removeNotification(Request $request){
        $input = $request->all();
        $millage = Notification::where('id',$input['id'])->where('user_id',$input['user_id'])->delete($input);
        return ['response'=>true, 'msg'=>'Notification deleted successfully!'];
    }
    
}
