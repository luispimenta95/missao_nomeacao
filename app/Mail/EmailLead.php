<?php

namespace App\Mail;

class EmailLead extends BaseEmail
{
    public $subject = 'Seu material da Missão Nomeação';

    /**
     * Retorna o template específico para o e-mail de lead / material.
     *
     * @return string
     */
    protected function getMarkdownTemplate()
    {
        return 'emails.template_lead';
    }
}
