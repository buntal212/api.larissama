<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('udev:dispatch-pending-customers')->everyMinute()->withoutOverlapping();
Schedule::command('udev:sync-invoices')->everyFiveMinutes()->withoutOverlapping(10);
Schedule::command('udev:recover-invoices')->everyThirtyMinutes()->withoutOverlapping(15);
