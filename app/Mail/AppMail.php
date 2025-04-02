<?php

namespace App\Mail;

//use Illuminate\Bus\Queueable;
//use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
//use Illuminate\Mail\Mailables\Content;
//use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// class AppMail extends Mailable
// {
//     use Queueable, SerializesModels;
//     public $subject = "Welcome to Laravel Mailgun!";
//     public $htmlString = "<h1>This is test</h1>";
//     /**
//      * Create a new message instance.
//      */
//     public function __construct($subject,$htmlString)
//     {
//         $this->subject = $subject;
//         $this->htmlString = $htmlString;
//     }

//     /**
//      * Get the message envelope.
//      */
//     public function envelope(): Envelope
//     {
//         return new Envelope(
//             subject: $this->subject,
//         );
//     }

//     /**
//      * Get the message content definition.
//      */
//     public function content(): Content
//     {
//         return new Content(
//             htmlString: $this->htmlString
//         );
//     }

//     /**
//      * Get the attachments for the message.
//      *
//      * @return array<int, \Illuminate\Mail\Mailables\Attachment>
//      */
//     public function attachments(): array
//     {
//         return [];
//     }
// }

class AppMail extends Mailable
{
    use SerializesModels;

    public $subject = "Test Email via Gmail SMTP";
    public $htmlString = "<h1>This is test</h1>";
    public $htmlBody = '<!DOCTYPE html><html><head><title></title></head><body><div><div><img src="https://taxitax.uk/public/image/taxi_logo.png" alt="Logo TaxiTax" width="200"></div><div>[EMAILBODY]</div><div><hr></div><div>© Taxitax.uk is managed and operated by Apptax Ltd, 71-75 Shelton Street, Covent Garden, London, United Kingdom, WC2H 9JQ.</div></div></body></html>';
    public function __construct($subject,$htmlString)
    {
        $this->subject = $subject;
        $this->htmlString = $htmlString;
    }

    public function build()
    {
        $html = str_replace('[EMAILBODY]',$this->htmlString,$this->htmlBody);
        return $this->html($html);
    }
}



