<?php
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log; // <-- Make sure this line is present at the top

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        Log::info('Scheduler: Schedule method is being evaluated.'); // <--- ADD THIS LINE

        $schedule->command('reports:dispatch-daily')
                 ->everyMinute()
                 ->appendOutputTo(storage_path('logs/dispatch_daily_reports_schedule.log'))
                 ->emailOutputOnFailure(env('MAIL_FROM_ADDRESS', 'buyamsellam2@gmail.com'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}