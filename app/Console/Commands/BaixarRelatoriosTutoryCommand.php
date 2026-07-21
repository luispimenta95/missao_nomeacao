<?php

namespace App\Console\Commands;

use App\Services\Tutory\CoachReportDownloader;
use Illuminate\Console\Command;
use Throwable;

class BaixarRelatoriosTutoryCommand extends Command
{
    protected $signature = 'tutory:baixar-relatorios
                            {--periodo= : 1 = dias 01–15; 2 = dia 16 até o último dia do mês}
                            {--teste : Baixa só o relatório da aluna Laíra Larceda}';

    protected $description = 'Baixa o Relatório do Coach (Tutory) e envia por e-mail aos alunos com recebe_email';

    public function handle(): int
    {
        $periodo = (string) $this->option('periodo');
        if (! in_array($periodo, ['1', '2'], true)) {
            $this->error('Informe --periodo=1 ou --periodo=2.');
            $this->line('  1 = Dia inicial: 01 / Dia final: 15');
            $this->line('  2 = Dia inicial: 16 / Dia final: último dia do mês');

            return self::FAILURE;
        }

        $teste = (bool) $this->option('teste');
        if ($teste) {
            $this->warn('Modo --teste ativo: processa apenas Laíra Larceda.');
        }

        try {
            $downloader = new CoachReportDownloader(
                periodo: $periodo,
                teste: $teste,
                logger: function (string $message): void {
                    $this->line($message);
                },
            );

            return $downloader->run();
        } catch (Throwable $exc) {
            $this->error($exc->getMessage());

            return self::FAILURE;
        }
    }
}
