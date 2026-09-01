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
| Periodo 1 (dias 01–15): todo dia 16 às 10:30
| Periodo 2 (dia 16–fim do mês anterior): dia 1 às 10:30
|
| Requer cron no servidor: * * * * * php /path/to/artisan schedule:run
|
*/

Schedule::command('tutory:baixar-relatorios --periodo=2')
    ->monthlyOn(01, '10:30')
    ->name('tutory-relatorios-periodo-2');

Schedule::command('tutory:baixar-relatorios --periodo=1')
    ->monthlyOn(16, '10:30')
    ->name('tutory-relatorios-periodo-1');
