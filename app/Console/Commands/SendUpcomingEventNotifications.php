<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AcademicCalendar;
use App\Models\User;
use App\Notifications\UpcomingEventNotification;
use Carbon\Carbon;

class SendUpcomingEventNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-upcoming-events';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notifications for upcoming academic calendar events.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tomorrow = Carbon::tomorrow()->toDateString();
        $events = AcademicCalendar::where('date', $tomorrow)->get();

        if ($events->isEmpty()) {
            $this->info('No upcoming events for tomorrow.');
            return;
        }

        $users = User::all(); // Or target specific users

        foreach ($events as $event) {
            foreach ($users as $user) {
                $user->notify(new UpcomingEventNotification($event));
            }
        }

        $this->info('Upcoming event notifications sent successfully.');
    }
}
