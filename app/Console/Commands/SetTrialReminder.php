<?php

namespace App\Console\Commands;

use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Auth as FirebaseAuth;

use App\Models\User;
use App\Models\Notification;
use Carbon\Carbon;

use Illuminate\Console\Command;

class SetTrialReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:set-trial-reminder';

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
        $yesterday = Carbon::yesterday();
        $yesterday->format('Y-m-d');
        $fromDate = $yesterday.' 00:00:00';
        $toDate = $yesterday.' 23:59:59';
        //$users = User::where('status','1')->where('role','customer')->whereBetween('created_at',['2024-11-13 00:00:00','2024-11-13 23:59:59'])->get();
        $users = User::where('status','1')->where('role','customer')->whereBetween('created_at',[$fromDate,$toDate])->get();
        if(isset($users)){
            foreach($users as $val)
            {
                $newDate = Carbon::parse($val['created_at'])->addDays(3);
                $title = "Trail account reminder";
                $body = "Hi ".$val['name'].", Your free 3 days TaxiTax App trial ends ".$newDate->format('d-m-Y')." and £".env('SUBSCRIPTION_PLAN_AMOUNT')." a month paid subscription will begin immediately.";

                $input["body"] = $body;
                $input["title"] = $title; 
                $input["user_id"] = $val['id']; 
                $notification = Notification::create($input);

                $device_token = $val['fcm_token'];
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
}
