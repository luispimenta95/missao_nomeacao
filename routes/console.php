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
| Quem decide a janela é tutory:executar-agendados (America/Sao_Paulo):
|   sincronizar alunos: todo dia a partir das 06:00, uma vez
|   período 1 (dias 01–15): dia 16, 10:30–22:59; retenta no dia 17, 11:00–22:59
|   período 2 (dia 16–fim): dia 1, 10:30–22:59; retenta no dia 2, 11:00–22:59
|   liberar períodos no admin: dias 1 e 16 a partir das 00:05, uma vez
|
| O Laravel não dispara sozinho. O deploy instala um cron na Hostinger:
|   * * * * * php artisan schedule:run
| Um tick depois do horário ainda executa o que ficou pendente naquele dia.
| O workflow .github/workflows/tutory-relatorios.yml é só um reforço: o
| schedule do GitHub atrasa ou descarta o evento e a run nem aparece.
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
