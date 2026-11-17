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
        // 読書計画の日次バッチ（期限切れへの更新 + リマインダー通知）を、毎日 20:00（日本時間）に実行する
        $schedule->command('reading-plans:notify')
            ->dailyAt('20:00')
            ->timezone('Asia/Tokyo');
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
