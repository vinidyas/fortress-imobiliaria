<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\FinanceMigrateAccountBalances;
use App\Console\Commands\FinanceMigratePaymentSchedules;
use App\Console\Commands\FinanceMigrateTransactions;
use App\Console\Commands\ImportLegacyData;
use App\Console\Commands\ImportMccLedger;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        ImportLegacyData::class,
        FinanceMigrateTransactions::class,
        FinanceMigratePaymentSchedules::class,
        FinanceMigrateAccountBalances::class,
        ImportMccLedger::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        //
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
