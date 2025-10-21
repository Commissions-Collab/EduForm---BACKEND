<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\AcademicCalendar;

class UpcomingEventNotification extends Notification
{
    use Queueable;

    protected $event;

    /**
     * Create a new notification instance.
     */
    public function __construct(AcademicCalendar $event)
    {
        $this->event = $event;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Upcoming Event: ' . $this->event->title,
            'message' => $this->event->description,
            'date' => $this->event->date,
        ];
    }
}
