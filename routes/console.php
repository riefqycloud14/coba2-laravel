<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
use Illuminate\Support\Facades\Schedule;

// Menjalankan sinkronisasi harian otomatis setiap jam 02:00 malam
Schedule::command('axapta:sync')->dailyAt('02:00');
