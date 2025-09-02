<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel {
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void {
        // $schedule->command('inspire')->hourly();

        $schedule->job(new \App\Jobs\TicketStats)->everyFiveMinutes(); //ogni 5 min

        for($i = 6; $i <= 17; $i++) {
            if($i < 10) {
                $j = "0$i";
            } else {
                $j = $i;
            }
            $schedule->job(new \App\Jobs\PlatformActivity)->dailyAt("$j:00");
        }

        // AUTO_ASSIGN_TICKET=true
        $isAutoAssignEnabled = env('AUTO_ASSIGN_TICKET', false);
        
        if($isAutoAssignEnabled) {
            $schedule->job(new \App\Jobs\AutoAssignTicket)->everyThirtyMinutes();
        }
    }



    /**
     * Register the commands for the application.
     */
    protected function commands(): void {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
