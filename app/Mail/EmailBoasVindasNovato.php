<?php

namespace App\Mail;

class EmailBoasVindasNovato extends BaseEmail
{
    public $subject = 'Boas-vindas à mentoria — Missão Nomeação';

    public function __construct(array $dados)
    {
        parent::__construct($dados);
    }

    protected function getMarkdownTemplate()
    {
        return 'emails.template_boas_vindas_novato';
    }
}
