<?php

namespace App\Console\Commands;

use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Auth as FirebaseAuth;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Billing;

class UpdateCancelledSubscribers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-cancelled-subscribers';

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
        //
        $users = User::where('status','4')->where('role','customer')->get();
        if(isset($users)){
            foreach($users as $user)
            {
                $billing = Billing::where('user_id', $user['id'])
                ->orderBy('invoice_date', 'desc')
                ->first();
                if ($billing && $billing->subscription_to) {
                    $nextbillingdate = $billing->subscription_to;
                } else {
                    $nextbillingdate = Carbon::parse($user['created_at'])->addDays(3)->getTimestamp();
                }
                if(time() > $nextbillingdate){
                    $user->update([
                        'status' => 5,
                    ]);
                    if($user['fcm_token'] !=''){
                        //$newDate = Carbon::parse($val['created_at'])->addDays(3);
                        $title = "SUBSCRIPTION_EXPIRED";
                        $body = "Hi ".$user['name'].", your subscription has expired. Please resubscribe now to continue using our services without interruption. If you think this is an error, please contact us at service@taxitax.uk.";
                        $device_token = $user['fcm_token'];
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
                            //echo "Error: " . $e->getMessage();
                        }
                    }
                }
            }
        }
    }
}
