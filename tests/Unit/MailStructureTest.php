<?php

namespace Tests\Unit;

use App\Mail\EmailInscricao;
use App\Mail\EmailLead;
use App\Mail\EmailRelatorioCoach;
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

    #[Test]
    public function email_relatorio_justifica_texto_sem_negrito(): void
    {
        $mail = new EmailRelatorioCoach([
            'to' => 'giovanna@example.com',
            'body' => [
                'nome' => 'Giovanna',
                'periodoLabel' => '01/09 a 15/09',
                'blocosDesempenho' => [
                    [
                        'titulo' => 'constância',
                        'texto' => 'Você estudou em 6 dos 15 dias analisados, deixando 9 dias sem estudar.',
                    ],
                    [
                        'titulo' => 'Assuntos abaixo da média',
                        'itens' => ['Direito Constitucional — 60%'],
                        'texto' => "• Direito Constitucional — 60%\n\nEsses dados vão ficar em acompanhamento.",
                        'cta' => [
                            'url' => 'https://example.com/analise',
                            'label' => 'Quero adiantar minha análise',
                        ],
                    ],
                ],
            ],
        ]);

        $html = $mail->render();
        $compact = preg_replace('/\s+/', '', $html) ?? $html;

        $this->assertMatchesRegularExpression('/\.contentp\{[^}]*text-align:justify/', $compact);
        $this->assertMatchesRegularExpression('/\.blocoh3\{[^}]*text-align:left/', $compact);
        $this->assertMatchesRegularExpression('/\.blocop\{[^}]*text-align:justify/', $compact);
        $this->assertMatchesRegularExpression('/\.blocoli\{[^}]*text-align:justify/', $compact);
        $this->assertStringContainsString('text-align:justify;', $html);
        $this->assertStringContainsString('<h3>constância</h3>', $html);
        $this->assertStringContainsString('text-align:left;', $html);
        $this->assertStringContainsString('.footer', $html);
        $this->assertStringContainsString('text-align: center;', $html);
        $this->assertStringContainsString('Esses dados vão ficar em acompanhamento.', $html);
    }
}
