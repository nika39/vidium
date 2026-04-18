<?php

use Illuminate\Support\Facades\Schedule;


Schedule::command('metrics:sync')->everyTenMinutes()->withoutOverlapping();
