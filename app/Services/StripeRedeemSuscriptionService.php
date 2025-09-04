<?php

namespace App\Services;
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
use Illuminate\Support\Facades\Log;

class StripeRedeemSuscriptionService
{
    protected $isPaused = false;
    protected $resumeDate = 0;

    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }
    public function isSuscriptionPaused($subscription_id){
        $subscription = \Stripe\Subscription::retrieve($subscription_id);
        if ($subscription->pause_collection) {
            $this->isPaused = true;
            if ($subscription->pause_collection->resumes_at) {
                $this->resumeDate = $subscription->pause_collection->resumes_at;
            }
        }
    }
    public function pauseTransaction($subscription_id){
        $this->isSuscriptionPaused($subscription_id);
        if($this->isPaused){
            $this->resumeDate = \Carbon\Carbon::createFromTimestamp($this->resumeDate)->addMonth()->timestamp;;
        }else{
            $this->resumeDate = now()->addMonth()->timestamp;
        }
        try{
            $inv = \Stripe\Subscription::update(
                $subscription_id,
                [
                    'pause_collection' => [
                        'behavior'   => 'mark_uncollectible',
                        'resumes_at' => $this->resumeDate,
                    ],
                ]
            );
        }catch (\Exception $e) {
            Log::error('Something went wrong', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);
        }
        
        return $this->getLatestRedeemedDiscountOnInvoice($subscription_id);
    }

    public function getLatestRedeemedDiscountOnInvoice($subscription_id){
        $subscription = \Stripe\Subscription::retrieve($subscription_id);
        $anchor = $subscription->current_period_end; 
        $months = 12; // forecast 12 months ahead
        $upcoming = [];
        for ($i = 1; $i <= $months; $i++) {
           $futureDate = \Carbon\Carbon::createFromTimestamp($anchor)->addMonths($i-1)->timestamp;
        
            if($futureDate <= $this->resumeDate ){
                try {
                    $upcoming[] = [
                        'next_payment' => \Carbon\Carbon::createFromTimestamp($futureDate)->format('Y-m-d')
                    ];
                }catch(\Exception $e){
                    echo $e;
                }
            }else{
                break;
            }
        }
        return $upcoming[count($upcoming)-1]['next_payment']; 
    }


}