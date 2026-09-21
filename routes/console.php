<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('about:salesgo', fn () => $this->comment('SalesGo API v1'))->purpose('Shows SalesGo API metadata');

Schedule::command('salesgo:aggregate-daily-sales')->hourly()->withoutOverlapping();
Schedule::command('salesgo:prune-location-pings')->dailyAt('02:15')->withoutOverlapping();
Schedule::command('salesgo:archive-monthly-reports')->dailyAt('02:45')->withoutOverlapping();
