<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public $token;
    public $email;

    public function __construct($token, $email)
    {
        $this->token = $token;
        $this->email = $email;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset Your EduForm Password');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.auth.reset',
            with: [
                'token' => $this->token,
                'email' => $this->email,
            ]
        );
    }
}
