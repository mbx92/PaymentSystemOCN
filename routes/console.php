<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('investments:fetch-prices')
    ->everyThirtyMinutes()
    ->between('9:00', '16:00')
    ->weekdays()
    ->withoutOverlapping();
