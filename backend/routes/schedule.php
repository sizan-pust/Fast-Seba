<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('engagement:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('finance:sync')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('delivery-cash:sync')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('bulk-uploads:run --limit=10')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('subscriptions:sync-usage')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('subscriptions:expire')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('orders:check-stuck')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('ads:settle-expired')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('ads:prune-dedup')
    ->dailyAt('02:10')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('bulk-uploads:prune')
    ->dailyAt('02:20')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('openapi:export')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('system:health-check')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();
