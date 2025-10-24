<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();

        $schedule->job(new \App\Jobs\TicketStats)->everyFiveMinutes(); //ogni 5 min

        foreach (['08:00', '12:00', '16:00'] as $time) {
            $schedule->job(new \App\Jobs\PlatformActivity)->dailyAt($time);
        }

        // AUTO_ASSIGN_TICKET=true
        $isAutoAssignEnabled = env('AUTO_ASSIGN_TICKET', false);

        if ($isAutoAssignEnabled) {
            $schedule->job(new \App\Jobs\AutoAssignTicket)->everyThirtyMinutes();
        }

        //Send billing reminders
        $schedule->job(new \App\Jobs\SendBillingReminders)->dailyAt('06:00');

        // Check hourly cost expiration weekly (every Monday at 08:00)
        $schedule->job(new \App\Jobs\CheckHourlyCostExpiration)->weeklyOn(1, '08:00');
            // Esegui FetchNewsForSource per ogni NewsSource ogni lunedì alle 7:00
            $schedule->call(function () {
                $sources = \App\Models\NewsSource::all();
                foreach ($sources as $source) {
                    dispatch(new \App\Jobs\FetchNewsForSource($source));
                }
            })->weeklyOn(1, '7:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
