<?php

namespace App\Http\Util;

use App\Mail\EmailInscricao;
use App\Mail\EmailLead;
use App\Mail\EmailRelatorioCoach;
use Illuminate\Support\Facades\Mail;

class MailHelper
{
    /**
     * Envia e-mail de confirmação de lead / material gratuito.
     *
     * @param  array  $dados  Dados do corpo (nome, tituloMaterial, url)
     * @param  string  $mailTo  Destinatário
     */
    public static function emailLead(array $dados, string $mailTo): void
    {
        $dadosEmail = [
            'to' => $mailTo,
            'body' => [
                'nome' => $dados['nome'],
                'tituloMaterial' => $dados['tituloMaterial'] ?? null,
                'url' => $dados['url'] ?? null,
            ],
        ];

        Mail::to($mailTo)->send(new EmailLead($dadosEmail));
    }

    /**
     * Envia e-mail de confirmação de inscrição em turma.
     *
     * @param  array  $dados  Dados do corpo (nome, tituloTurma, url)
     * @param  string  $mailTo  Destinatário
     */
    public static function emailInscricao(array $dados, string $mailTo): void
    {
        $dadosEmail = [
            'to' => $mailTo,
            'body' => [
                'nome' => $dados['nome'],
                'tituloTurma' => $dados['tituloTurma'] ?? null,
                'url' => $dados['url'] ?? null,
            ],
        ];

        Mail::to($mailTo)->send(new EmailInscricao($dadosEmail));
    }

    /**
     * Envia os Relatórios do Coach com PDFs em anexo (um único e-mail).
     *
     * @param  array{nome: string, periodoLabel?: string, relatorios?: list<string>}  $dados
     * @param  string|list<string>  $pdfPath  Um caminho ou lista de PDFs
     */
    public static function emailRelatorioCoach(array $dados, string $mailTo, string|array $pdfPath): void
    {
        $dadosEmail = [
            'to' => $mailTo,
            'body' => [
                'nome' => $dados['nome'],
                'periodoLabel' => $dados['periodoLabel'] ?? null,
                'relatorios' => $dados['relatorios'] ?? null,
            ],
        ];

        Mail::to($mailTo)->send(new EmailRelatorioCoach($dadosEmail, $pdfPath));
    }
}
