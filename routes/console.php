<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Schedule::command('metrics:sync')
    ->everyTenMinutes()
    ->onFailure(fn () => Log::error('Metrics sync scheduled task failed'))
    ->withoutOverlapping();
