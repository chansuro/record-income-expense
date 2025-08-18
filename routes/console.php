<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\InsertRecurringTransactions;
use App\Console\Commands\InsertMonthlyRecurringTransaction;
use App\Console\Commands\SendReminderNotification;
use App\Console\Commands\SetTrialReminder;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command(InsertRecurringTransactions::class)->days([1])->at('4:00');
Schedule::command(InsertMonthlyRecurringTransaction::class)->monthly();
Schedule::command(SendReminderNotification::class)->hourly();
Schedule::command(SetTrialReminder::class)->dailyAt('07:00');