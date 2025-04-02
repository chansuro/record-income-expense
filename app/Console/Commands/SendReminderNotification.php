<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Auth as FirebaseAuth;
use App\Models\Reminder;
use Illuminate\Support\Facades\Log;


class SendReminderNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-reminder-notification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $weekarr = [0=>'sun',1=>'mon',2=>'tue',3=>'wed',4=>'thu',5=>'fri',6=>'sat'];
        $todayWeek =  $weekarr[date('w')];

        $reminders = Reminder::join('users','users.id','=','reminders.user_id')
        ->selectRaw("users.fcm_token")
        ->where('reminders.is_alerm','Y')
        ->where('users.status', 1)
        ->where('reminders.reminder_time', date('G').':00')
        ->whereRaw('FIND_IN_SET("'.$todayWeek.'", reminders.repeat_on)')
        ->get();
        //Log::info('This is an informational message',[$reminders]);
        if($reminders){
            foreach($reminders as $value)
            {
                if($value->fcm_token != '' || $value->fcm_token != null){
                    $title = "Reminder notification";
                    $body = "Reminver notification body";
                    $device_token = $value->fcm_token;
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
                }
            }
        }
        //
        // $title = "Reminder notification";
        // $body = "Reminver notification body";
        // $device_token = "test------ceOa4U1LSxGg6jwKsKJv-n:APA91bEXSw5Jpo_O9OPT4GlY2hOWZCiDIWxadLGWdulas7x-7jmvjH7ohaEYrFi368VBOi07XgNmOIAaU9bhNMcwej46hNB7InHSnuGYh2Q_UHbz8KJKJKY";
        // $factory = (new Factory)->withServiceAccount(storage_path(env('FIREBASE_CREDENTIALS')));
        // $messaging = $factory->createMessaging();

        // // Create a notification message
        // $message = CloudMessage::withTarget('token', $device_token)
        // ->withNotification(['title'=>$title, 'body'=>$body])
        // ->withData(['test' => 'testing']);
        // try {
        //     $response = $messaging->send($message);
        //     echo "Notification sent!";
        // } catch (\Kreait\Firebase\Exception\Messaging\FailedToSendNotification $e) {
        //     echo "Error: " . $e->getMessage();
        // }
    }
}
