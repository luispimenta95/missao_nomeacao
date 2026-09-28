<?php

namespace App\Services\Tutory;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Dispara o scheduler no fim do request HTTP.
 *
 * No plano compartilhado da Hostinger não há crontab pelo SSH, e o schedule
 * do GitHub Actions descarta o evento sem criar run. A visita ao site (admin
 * ou landing) é o relógio que existe de fato: a resposta já foi enviada, e
 * tutory:executar-agendados recupera o que passou do horário.
 */
class TutorySchedulerKick
{
    public function disparar(): void
    {
        try {
            if (! Cache::add('tutory.scheduler.kick', '1', 55)) {
                return;
            }
        } catch (Throwable) {
            // Sem cache o executor ainda trava o sync do dia.
        }

        set_time_limit(0);
        ignore_user_abort(true);
        // O PDF fica no cron/SSH: o PHP do site corta a execução e reenviaria e-mail.
        Artisan::call('tutory:executar-agendados', ['--sem-relatorios' => true]);
    }
}
