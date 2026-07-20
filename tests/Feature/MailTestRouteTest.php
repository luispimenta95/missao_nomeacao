<?php

namespace Tests\Feature;

use App\Mail\EmailLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailTestRouteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function teste_email_route_sends_mail_and_shows_success(): void
    {
        Mail::fake();

        $response = $this->get('/teste-email');

        $response->assertOk();
        $response->assertSee('SUCESSO');
        $response->assertSee('luispimenta.contato@gmail.com');

        Mail::assertSent(EmailLead::class, function (EmailLead $mail) {
            return $mail->mailTo === 'luispimenta.contato@gmail.com';
        });
    }

    #[Test]
    public function teste_email_route_shows_error_when_send_fails(): void
    {
        $pending = \Mockery::mock(\Illuminate\Mail\PendingMail::class);
        $pending->shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('SMTP connection refused'));

        Mail::shouldReceive('to')
            ->once()
            ->with('luispimenta.contato@gmail.com')
            ->andReturn($pending);

        $response = $this->get('/teste-email');

        $response->assertOk();
        $response->assertSee('ERRO');
        $response->assertSee('SMTP connection refused');
    }
}
