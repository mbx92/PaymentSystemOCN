<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('investments:fetch-prices')
    ->everyThirtyMinutes()
    ->between('9:00', '16:00')
    ->weekdays()
    ->withoutOverlapping();

Schedule::command('supplier-catalog:sync')
    ->dailyAt(config('supplier_catalog.sync_time', '02:00'))
    ->withoutOverlapping();
