<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The test email of "php artisan mail:gmail": it arriving shows the Gmail account can send the
 * app's emails, such as the links to reset a password.
 */
class MailSetupCheck extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $senderAddress) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tes email '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.setup-check');
    }
}
