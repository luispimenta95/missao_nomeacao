<?php

use App\Models\Configuracao;
use App\Services\Tutory\TutoryAgendaDoDia;
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
| Quem decide o horário é tutory:executar-agendados (America/Sao_Paulo, UTC−3):
|   sincronizar alunos: todo dia às 06:00
|   período 1 (dias 01–15): dia 16 às 10:30; retenta de hora em hora, 11:00–22:00, nos dias 16 e 17
|   período 2 (dia 16–fim): dia 1 às 10:30; retenta de hora em hora, 11:00–22:00, nos dias 1 e 2
|   liberar períodos no admin: dias 1 e 16 às 00:05
| Cada horário aceita 4 minutos de atraso do tick. 11:05 não roda o job das 06:00.
|
| O relógio é o cron de todo minuto na Hostinger (`scripts/tutory-scheduler.sh`).
| O schedule do GitHub só chama o artisan nesses mesmos horários, em UTC.
|
*/

$logTutory = storage_path('logs/tutory-schedule.log');

Schedule::command('tutory:executar-agendados')
    ->everyMinute()
    ->timezone('America/Sao_Paulo')
    ->name('tutory-executar-agendados')
    ->withoutOverlapping(180)
    ->appendOutputTo($logTutory)
    ->onFailure(fn () => Log::error('[scheduler] tutory:executar-agendados falhou'));

Schedule::call(function () {
    Configuracao::definir(
        TutoryAgendaDoDia::HEARTBEAT,
        now('America/Sao_Paulo')->toIso8601String()
    );
})->everyMinute()
    ->timezone('America/Sao_Paulo')
    ->name('tutory-heartbeat');
