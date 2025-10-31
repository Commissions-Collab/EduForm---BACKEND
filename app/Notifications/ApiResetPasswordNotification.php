<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Mail\PasswordResetMail;
use Illuminate\Notifications\Notification;
// Mail facade not needed when returning a Mailable instance

class ApiResetPasswordNotification extends Notification
{
    use Queueable;

    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        // Return the PasswordResetMail mailable so Laravel sends it once via the
        // notification mail channel. Avoid calling Mail::send(...) here because
        // notify() will also send the returned mail, which caused duplicate emails.
        // Ensure the mailable has a recipient (To header) — set to the notifiable's email
        return (new PasswordResetMail($this->token, $notifiable->email))->to($notifiable->email);
    }
}
