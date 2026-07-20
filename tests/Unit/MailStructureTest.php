<?php

namespace Tests\Unit;

use App\Mail\EmailInscricao;
use App\Mail\EmailLead;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailStructureTest extends TestCase
{
    #[Test]
    public function base_email_rejects_invalid_recipient(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EmailLead([
            'to' => 'email-invalido',
            'body' => ['nome' => 'Teste'],
        ]);
    }

    #[Test]
    public function email_lead_uses_expected_template_and_subject(): void
    {
        $mail = new EmailLead([
            'to' => 'lead@example.com',
            'body' => [
                'nome' => 'Maria',
                'tituloMaterial' => 'PDF Gratuito',
                'url' => 'https://example.com/download',
            ],
        ]);

        $this->assertSame('Seu material da Missão Nomeação', $mail->subject);
        $this->assertSame('lead@example.com', $mail->mailTo);

        $built = $mail->build();
        $this->assertSame('emails.template_lead', $built->view);
    }

    #[Test]
    public function email_inscricao_uses_expected_template_and_subject(): void
    {
        $mail = new EmailInscricao([
            'to' => 'aluno@example.com',
            'body' => [
                'nome' => 'João',
                'tituloTurma' => 'Turma Janeiro',
                'url' => 'https://example.com/checkout',
            ],
        ]);

        $this->assertSame('Inscrição recebida — Missão Nomeação', $mail->subject);
        $this->assertSame('aluno@example.com', $mail->mailTo);

        $built = $mail->build();
        $this->assertSame('emails.template_inscricao', $built->view);
    }
}
