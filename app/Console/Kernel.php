<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\CleanupDownloads::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // Clean up expired downloads every hour
        $schedule->command('downloads:cleanup')->hourly();
        
        // Clean up failed queue jobs
        $schedule->command('queue:prune-failed --hours=48')->daily();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}