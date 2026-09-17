<?php

namespace App\Services\Tutory;

use App\Models\Aluno;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Gera o PDF consolidado de um aluno para um período já liberado,
 * sem envio de e-mail (contingência do admin).
 */
class RelatorioPdfContingencia
{
    /**
     * @param  array{year_month: string, period: string}  $periodo
     */
    public function gerar(Aluno $aluno, array $periodo, ?callable $logger = null): string
    {
        $tutoryId = trim((string) $aluno->tutory_id);
        if ($tutoryId === '') {
            throw new RuntimeException('Aluno sem id da Tutory. Sincronize os alunos ativos e tente de novo.');
        }

        $mes = \DateTimeImmutable::createFromFormat('Y-m-d', $periodo['year_month'].'-01');
        if ($mes === false) {
            throw new RuntimeException('Mês do período inválido.');
        }

        $downloader = new CoachReportDownloader(
            periodo: (string) $periodo['period'],
            logger: $logger ?? static function (string $message): void {
                Log::info('[pdf-contingencia] '.$message);
            },
            teste: false,
            referenciaMes: $mes,
        );

        return $downloader->gerarPdfParaAluno($tutoryId, $aluno->nome);
    }
}
