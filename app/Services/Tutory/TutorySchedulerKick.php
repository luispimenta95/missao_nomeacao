<?php

namespace App\Services\Tutory;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Dispara o scheduler no fim do request HTTP.
 *
 * A visita só dispara o job se o relógio de America/Sao_Paulo estiver no
 * minuto agendado (ou nos 4 minutos seguintes). Não recupera um horário
 * que já passou.
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
