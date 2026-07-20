<?php

namespace App\Http\Util;

use App\Mail\EmailInscricao;
use App\Mail\EmailLead;
use Illuminate\Support\Facades\Mail;

class MailHelper
{
    /**
     * Envia e-mail de confirmação de lead / material gratuito.
     *
     * @param array $dados Dados do corpo (nome, tituloMaterial, url)
     * @param string $mailTo Destinatário
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
     * @param array $dados Dados do corpo (nome, tituloTurma, url)
     * @param string $mailTo Destinatário
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
}
