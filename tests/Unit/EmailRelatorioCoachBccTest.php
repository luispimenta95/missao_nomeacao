<?php

namespace Tests\Unit;

use App\Http\Util\MailHelper;
use App\Mail\EmailInscricao;
use App\Mail\EmailLead;
use App\Mail\EmailRelatorioCoach;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailRelatorioCoachBccTest extends TestCase
{
    #[Test]
    public function cco_padrao_e_nayara(): void
    {
        $this->assertSame(
            ['nayara@missaonomeacao.com.br'],
            MailHelper::enderecosCco()
        );
    }

    #[Test]
    public function cco_aceita_varios_enderecos_e_ignora_o_destinatario(): void
    {
        config(['mail.bcc.address' => 'nayara@missaonomeacao.com.br, luis@missaonomeacao.com.br, aluno@example.com']);

        $this->assertSame(
            ['nayara@missaonomeacao.com.br', 'luis@missaonomeacao.com.br'],
            MailHelper::enderecosCco('aluno@example.com')
        );
    }

    #[Test]
    public function cco_vazio_desliga_a_copia(): void
    {
        config(['mail.bcc.address' => '']);

        $this->assertSame([], MailHelper::enderecosCco());
    }

    #[Test]
    public function envio_do_relatorio_inclui_cco(): void
    {
        Mail::fake();
        config(['mail.bcc.address' => 'nayara@missaonomeacao.com.br']);

        MailHelper::emailRelatorioCoach(
            ['nome' => 'Maria'],
            'maria@example.com',
            []
        );

        Mail::assertSent(EmailRelatorioCoach::class, function (EmailRelatorioCoach $mail): bool {
            return $mail->hasTo('maria@example.com')
                && $mail->hasBcc('nayara@missaonomeacao.com.br');
        });
    }

    #[Test]
    public function envio_de_lead_e_inscricao_inclui_cco(): void
    {
        Mail::fake();
        config(['mail.bcc.address' => 'nayara@missaonomeacao.com.br']);

        MailHelper::emailLead(
            ['nome' => 'Maria', 'tituloMaterial' => 'PDF', 'url' => 'https://example.com'],
            'maria@example.com'
        );
        MailHelper::emailInscricao(
            ['nome' => 'João', 'tituloTurma' => 'Turma', 'url' => 'https://example.com'],
            'joao@example.com'
        );

        Mail::assertSent(EmailLead::class, function (EmailLead $mail): bool {
            return $mail->hasTo('maria@example.com')
                && $mail->hasBcc('nayara@missaonomeacao.com.br');
        });
        Mail::assertSent(EmailInscricao::class, function (EmailInscricao $mail): bool {
            return $mail->hasTo('joao@example.com')
                && $mail->hasBcc('nayara@missaonomeacao.com.br');
        });
    }

    #[Test]
    public function envio_sem_cco_quando_config_vazia(): void
    {
        Mail::fake();
        config(['mail.bcc.address' => '']);

        MailHelper::emailRelatorioCoach(
            ['nome' => 'Maria'],
            'maria@example.com',
            []
        );

        Mail::assertSent(EmailRelatorioCoach::class, function (EmailRelatorioCoach $mail): bool {
            return $mail->hasTo('maria@example.com')
                && $mail->hasBcc('nayara@missaonomeacao.com.br') === false;
        });
    }
}
