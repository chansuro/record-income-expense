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
            config('services.twilio.sid'),
            config('services.twilio.token')
        );

        $this->twilioNumber = config('services.twilio.phone');
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