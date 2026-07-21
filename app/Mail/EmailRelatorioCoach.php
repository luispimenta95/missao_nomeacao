<?php

namespace App\Mail;

class EmailRelatorioCoach extends BaseEmail
{
    public $subject = 'Seu Relatório do Coach - Missão Nomeação';

    private ?string $anexoPath;

    public function __construct(array $dados, ?string $anexoPath = null)
    {
        parent::__construct($dados);
        $this->anexoPath = $anexoPath;

        $periodoLabel = $dados['body']['periodoLabel'] ?? null;
        if (is_string($periodoLabel) && $periodoLabel !== '') {
            $this->subject = 'Relatório do Coach ('.$periodoLabel.') - Missão Nomeação';
        }
    }

    public function build()
    {
        $mail = parent::build();

        if ($this->anexoPath !== null && is_file($this->anexoPath)) {
            $mail->attach($this->anexoPath, [
                'as' => basename($this->anexoPath),
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }

    protected function getMarkdownTemplate()
    {
        return 'emails.template_relatorio_coach';
    }
}
