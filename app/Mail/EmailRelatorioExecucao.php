<?php

namespace App\Mail;

class EmailRelatorioExecucao extends BaseEmail
{
    public $subject = 'Relatório de execução Tutory';

    private string $anexoPath;

    public function __construct(array $dados, string $anexoPath)
    {
        parent::__construct($dados);
        $this->anexoPath = $anexoPath;
        $data = trim((string) ($this->dados['data'] ?? ''));
        $this->subject = $data === ''
            ? 'Relatório de execução Tutory'
            : 'Relatório de execução Tutory — '.$data;
    }

    public function build()
    {
        $mail = parent::build();
        if (is_file($this->anexoPath)) {
            $mail->attach($this->anexoPath, [
                'as' => basename($this->anexoPath),
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }

    protected function getMarkdownTemplate()
    {
        return 'emails.template_relatorio_execucao';
    }
}
