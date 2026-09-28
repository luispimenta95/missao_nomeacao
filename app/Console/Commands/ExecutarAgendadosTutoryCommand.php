<?php

namespace App\Console\Commands;

use App\Services\Tutory\TutoryAgendaExecutor;
use Illuminate\Console\Command;

class ExecutarAgendadosTutoryCommand extends Command
{
    protected $signature = 'tutory:executar-agendados {--sem-relatorios : Não gera PDF; usado no fim do request web, onde o PHP é interrompido}';

    protected $description = 'Roda os jobs da Tutory que já abriram a janela hoje e ainda não concluíram (sync 06:00, PDF, períodos)';

    public function handle(TutoryAgendaExecutor $executor): int
    {
        $resultado = $executor->executar(
            now('America/Sao_Paulo'),
            fn (string $comando, array $argumentos): int => $this->call($comando, $argumentos),
            ! $this->option('sem-relatorios'),
        );

        foreach ($resultado['executados'] as $id) {
            $this->info('Executado: '.$id);
        }
        foreach ($resultado['falhas'] as $id) {
            $this->error('Falhou: '.$id);
        }

        return $resultado['falhas'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
