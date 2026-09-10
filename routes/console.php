<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sends "how was your workspace today?" survey emails once a day, for any
// booking that ended in the last 24 hours and hasn't been surveyed yet.
// For this to actually run automatically, XAMPP needs Windows Task
// Scheduler (or cron on Linux) calling `php artisan schedule:run` every
// minute — see the README for the exact command. Until that's set up, you
// can trigger it manually any time with: php artisan surveys:send
Schedule::command('surveys:send')->dailyAt('20:00');

// Uncomment once you've tested `php artisan legacy:sync-customers` manually
// and are happy with what it does — keeps picking up new signups from the
// old site automatically while both are live during the transition.
// Schedule::command('legacy:sync-customers')->hourly();
