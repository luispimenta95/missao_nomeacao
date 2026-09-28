<?php

namespace App\Console\Commands;

use App\Models\Configuracao;
use App\Services\Tutory\TutoryAgendaDoDia;
use App\Services\Tutory\TutoryRelatorioAgenda;
use Illuminate\Console\Command;
use Throwable;

class TutorySchedulerStatusCommand extends Command
{
    protected $signature = 'tutory:scheduler-status';

    protected $description = 'Mostra fuso, próxima janela do scheduler e se o período já foi enviado';

    public function handle(): int
    {
        $tz = (string) config('app.timezone');
        $agora = now()->timezone($tz);
        $this->info('APP_TIMEZONE: '.$tz);
        $this->info('Agora: '.$agora->toDateTimeString());
        $this->line('Heartbeat do cron: '.$this->valorEnvio(TutoryAgendaDoDia::HEARTBEAT));
        $this->line('Sync de hoje: '.$this->valorEnvio('tutory.job.sincronizar-alunos.'.$agora->format('Y-m-d')));
        $this->line('Liberar períodos de hoje: '.$this->valorEnvio('tutory.job.liberar-periodos.'.$agora->format('Y-m-d')));
        $this->alertarCronParado();
        $this->newLine();

        foreach (['1', '2'] as $periodo) {
            $chave = TutoryRelatorioAgenda::chaveEnvio($periodo);
            $this->line("Período {$periodo} · {$chave}");
            $this->line('  '.$this->valorEnvio($chave));
        }

        $this->newLine();
        try {
            $this->call('schedule:list');
        } catch (Throwable $exc) {
            $this->error('schedule:list falhou: '.$exc->getMessage());
        }
        $this->newLine();
        $this->comment('Sem cron, uma visita ao site dispara tutory:executar-agendados depois da resposta.');
        $this->comment('No hPanel, o cron de todo minuto deve chamar scripts/tutory-scheduler.sh.');
        $this->comment('O workflow GitHub "Tutory Relatorios" só reforça, quando o schedule dele chega a criar uma run.');

        return self::SUCCESS;
    }

    private function alertarCronParado(): void
    {
        $heart = Configuracao::valor(TutoryAgendaDoDia::HEARTBEAT);
        if ($heart === null || $heart === '') {
            $this->warn('Sem heartbeat. O cron ainda não chamou schedule:run neste servidor.');

            return;
        }

        $ts = strtotime($heart);
        if ($ts !== false && (time() - $ts) > 600) {
            $this->warn('O cron está atrasado mais de 10 minutos. Veja storage/logs/schedule-run.log e o crontab.');
        }
    }

    private function valorEnvio(string $chave): string
    {
        try {
            return Configuracao::valor($chave) ?? '(nunca enviado neste período)';
        } catch (Throwable $exc) {
            return '(não leu configuracoes: '.$exc->getMessage().')';
        }
    }
}
