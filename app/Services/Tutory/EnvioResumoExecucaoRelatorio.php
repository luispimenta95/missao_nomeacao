<?php

namespace App\Services\Tutory;

use App\Http\Util\MailHelper;

class EnvioResumoExecucaoRelatorio
{
    public function enviar(ResumoExecucaoRelatorio $resumo): ?string
    {
        $destino = mb_strtolower(trim((string) config('mail.relatorio_execucao_address')));
        if ($destino === '' || ! filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $pdf = (new RelatorioExecucaoPdf)->salvar($resumo);
        $fim = $resumo->fim ?? $resumo->inicio;

        MailHelper::emailRelatorioExecucao(
            [
                'data' => $resumo->inicio->format('d/m/Y'),
                'periodo' => $resumo->periodoRotulo,
                'inicio' => $resumo->inicio->format('H:i:s'),
                'fim' => $fim->format('H:i:s'),
                'duracao' => $resumo->duracao(),
            ],
            $destino,
            $pdf
        );

        return $pdf;
    }
}
