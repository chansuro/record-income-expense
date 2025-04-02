<?php

namespace App\Services;

use Twilio\Rest\Client;

class TwilioService
{
    protected $client;
    protected $twilioNumber;

    public function __construct()
    {
        $this->client = new Client(
            env('TWILIO_SID'),
            env('TWILIO_AUTH_TOKEN')
        );

        $this->twilioNumber = env('TWILIO_PHONE_NUMBER');
    }

    public function sendSms($to, $message)
    {
        try {
            $message = $this->client->messages->create(
                $to, // Recipient phone number
                [
                    'from' => $this->twilioNumber,
                    'body' => $message
                ]
            );

            return $message->sid;
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }
}