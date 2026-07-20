<?php

namespace App\Mail;

class EmailInscricao extends BaseEmail
{
    public $subject = 'Inscrição recebida — Missão Nomeação';

    /**
     * Retorna o template específico para o e-mail de inscrição.
     *
     * @return string
     */
    protected function getMarkdownTemplate()
    {
        return 'emails.template_inscricao';
    }
}
