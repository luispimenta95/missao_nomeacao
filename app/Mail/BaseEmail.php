<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Classe base para todos os emails do sistema
 *
 * Define a estrutura comum para envio de emails, incluindo
 * validação de destinatário, configuração do remetente e
 * construção padrão do email com template dinâmico.
 */
abstract class BaseEmail extends Mailable
{
    use Queueable, SerializesModels;

    protected $dados;
    protected $sender;
    protected $fromName = 'Missão Nomeação';
    public $subject;
    public $mailTo;

    /**
     * Cria uma nova instância do e-mail
     *
     * Valida o endereço de email do destinatário e inicializa
     * os dados necessários para construção do email.
     *
     * @param array $dados Array contendo 'to' (destinatário) e 'body' (dados do corpo)
     * @throws \InvalidArgumentException Se o email do destinatário for inválido
     */
    public function __construct(array $dados)
    {
        if (!isset($dados['to']) || !filter_var($dados['to'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("O campo 'to' deve conter um endereço de e-mail válido.");
        }

        $this->dados = $dados['body'] ?? [];
        $this->mailTo = $dados['to'];
        $this->sender = env('MAIL_FROM_ADDRESS', config('mail.from.address'));
        $this->fromName = env('MAIL_FROM_NAME', config('mail.from.name', $this->fromName));
    }

    /**
     * Constrói o conteúdo do e-mail
     *
     * Define o remetente, assunto, template e dados que serão
     * passados para a view do email.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from($this->sender, $this->fromName)
            ->subject($this->subject)
            ->view($this->getMarkdownTemplate())
            ->with('dados', $this->dados);
    }

    /**
     * Retorna o template específico para cada e-mail
     *
     * Método abstrato que deve ser implementado pelas classes filhas
     * para definir qual template será usado para o email específico.
     *
     * @return string Caminho para o template do email
     */
    abstract protected function getMarkdownTemplate();
}
