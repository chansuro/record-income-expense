<?php

namespace App\Services;

use Kreait\Firebase\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\MessagingException;

class FirebaseService
{
    protected $messaging;

    public function __construct()
    {
        // Initialize Firebase messaging
        $this->messaging = (new Factory)->createMessaging();
    }

    /**
     * Send push notification to a device.
     *
     * @param string $deviceToken
     * @param string $title
     * @param string $body
     * @return string
     */
    public function sendNotification(string $deviceToken, string $title, string $body)
    {
        try {
            // Create the message payload
            $message = Messaging\CloudMessage::withTarget('token', $deviceToken)
                ->withNotification(Messaging\Notification::create($title, $body));

            // Send the message
            $this->messaging->send($message);

            return 'Notification sent successfully!';
        } catch (MessagingException $e) {
            return 'Error sending notification: ' . $e->getMessage();
        }
    }
}
