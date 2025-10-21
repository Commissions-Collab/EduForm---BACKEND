<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Registration Approved - Welcome to AcadFlow!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.registration-approved',
            with: [
                'user' => $this->user,
                'loginUrl' => config('app.frontend_url') . '/sign-in',
            ]
        );
    }
}
