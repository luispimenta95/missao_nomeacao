<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Relatórios do Coach (Tutory)
|--------------------------------------------------------------------------
|
| Periodo 1 (dias 01–15): todo dia 16 às 00:00
| Periodo 2 (dia 16–fim): às 00:00 do último dia do mês
|
| Requer cron no servidor: * * * * * php /path/to/artisan schedule:run
|
*/

Schedule::command('tutory:baixar-relatorios --periodo=1')
    ->monthlyOn(16, '00:00')
    ->name('tutory-relatorios-periodo-1');

Schedule::command('tutory:baixar-relatorios --periodo=2')
    ->lastDayOfMonth('00:00')
    ->name('tutory-relatorios-periodo-2');
