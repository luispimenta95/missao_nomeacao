<?php

namespace App\Mail;

class EmailRelatorioCoach extends BaseEmail
{
    public $subject = 'Seus Relatórios da Mentoria - Missão Nomeação';

    /** @var list<string> */
    private array $anexoPaths;

    /**
     * @param  string|list<string>|null  $anexoPath
     */
    public function __construct(array $dados, string|array|null $anexoPath = null)
    {
        parent::__construct($dados);
        if (is_string($anexoPath) && $anexoPath !== '') {
            $this->anexoPaths = [$anexoPath];
        } elseif (is_array($anexoPath)) {
            $this->anexoPaths = array_values(array_filter(
                $anexoPath,
                static fn($p): bool => is_string($p) && $p !== ''
            ));
        } else {
            $this->anexoPaths = [];
        }

        $this->subject = 'Relatórios da Mentoria - Missão Nomeação';
    }

    public function build()
    {
        $mail = parent::build();

        foreach ($this->anexoPaths as $path) {
            if (is_file($path)) {
                $mail->attach($path, [
                    'as' => basename($path),
                    'mime' => 'application/pdf',
                ]);
            }
        }

        return $mail;
    }

    protected function getMarkdownTemplate()
    {
        return 'emails.template_relatorio_coach';
    }
}
