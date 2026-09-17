<?php

namespace App\Console\Commands;

use App\Models\Configuracao;
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
        $this->info('APP_TIMEZONE: '.$tz);
        $this->info('Agora: '.now()->timezone($tz)->toDateTimeString());
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
        $this->comment('O agendamento do Laravel só dispara se o cron da Hostinger chamar `php artisan schedule:run` a cada minuto.');
        $this->comment('O workflow GitHub "Tutory Relatorios" chama o artisan direto por SSH, sem depender desse cron.');

        return self::SUCCESS;
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
