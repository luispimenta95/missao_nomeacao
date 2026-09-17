<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Relatórios do Coach (Tutory)
|--------------------------------------------------------------------------
|
| Periodo 1 (dias 01–15): todo dia 16 às 10:30 (America/Sao_Paulo)
| Periodo 2 (dia 16–fim do mês anterior): dia 1 às 10:30
| Sincronizar alunos ativos da Tutory: dias 1 e 16 às 06:00
| Liberar períodos no admin (PDF de meses anteriores): todo dia às 00:05
|
| O Laravel NÃO dispara sozinho. Sem `php artisan schedule:run` a cada
| minuto no cron da Hostinger, estes horários nunca executam.
| O workflow .github/workflows/tutory-relatorios.yml chama o artisan
| por SSH e é o disparo principal.
|
*/

$logTutory = storage_path('logs/tutory-schedule.log');

Schedule::command('tutory:baixar-relatorios --periodo=2 --se-pendente')
    ->monthlyOn(1, '10:30')
    ->timezone('America/Sao_Paulo')
    ->name('tutory-relatorios-periodo-2')
    ->withoutOverlapping(180)
    ->appendOutputTo($logTutory)
    ->onFailure(fn () => Log::error('[scheduler] tutory:baixar-relatorios --periodo=2 falhou'));

Schedule::command('tutory:baixar-relatorios --periodo=1 --se-pendente')
    ->monthlyOn(16, '10:30')
    ->timezone('America/Sao_Paulo')
    ->name('tutory-relatorios-periodo-1')
    ->withoutOverlapping(180)
    ->appendOutputTo($logTutory)
    ->onFailure(fn () => Log::error('[scheduler] tutory:baixar-relatorios --periodo=1 falhou'));

Schedule::command('tutory:baixar-relatorios --periodo=1 --se-pendente')
    ->hourly()
    ->timezone('America/Sao_Paulo')
    ->between('11:00', '22:00')
    ->when(fn () => in_array((int) now('America/Sao_Paulo')->day, [16, 17], true))
    ->name('tutory-relatorios-periodo-1-retentativa')
    ->withoutOverlapping(180)
    ->appendOutputTo($logTutory);

Schedule::command('tutory:baixar-relatorios --periodo=2 --se-pendente')
    ->hourly()
    ->timezone('America/Sao_Paulo')
    ->between('11:00', '22:00')
    ->when(fn () => in_array((int) now('America/Sao_Paulo')->day, [1, 2], true))
    ->name('tutory-relatorios-periodo-2-retentativa')
    ->withoutOverlapping(180)
    ->appendOutputTo($logTutory);

Schedule::command('tutory:sincronizar-alunos')
    ->monthlyOn(1, '06:00')
    ->timezone('America/Sao_Paulo')
    ->name('tutory-sincronizar-alunos-dia-1')
    ->appendOutputTo($logTutory);

Schedule::command('tutory:sincronizar-alunos')
    ->monthlyOn(16, '06:00')
    ->timezone('America/Sao_Paulo')
    ->name('tutory-sincronizar-alunos-dia-16')
    ->appendOutputTo($logTutory);

Schedule::command('tutory:liberar-periodos-pdf')
    ->dailyAt('00:05')
    ->timezone('America/Sao_Paulo')
    ->name('tutory-liberar-periodos-pdf')
    ->appendOutputTo($logTutory);
