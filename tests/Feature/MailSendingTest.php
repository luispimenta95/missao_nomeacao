<?php

namespace Tests\Feature;

use App\Mail\EmailInscricao;
use App\Mail\EmailLead;
use App\Models\Material;
use App\Models\Turma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailSendingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function storing_a_lead_sends_lead_email(): void
    {
        Mail::fake();

        $material = Material::create([
            'title' => 'Material Teste',
            'description' => 'Descrição',
            'file_path' => 'materials/teste.pdf',
            'link' => 'https://example.com/material',
        ]);

        $response = $this->postJson('/leads', [
            'name' => 'Fulano de Tal',
            'email' => 'fulano@example.com',
            'phone' => '11999999999',
            'consent' => 'on',
            'material_id' => $material->id,
        ]);

        $response->assertStatus(200);

        Mail::assertSent(EmailLead::class, function (EmailLead $mail) {
            return $mail->mailTo === 'fulano@example.com'
                && $mail->subject === 'Seu material da Missão Nomeação';
        });
    }

    #[Test]
    public function storing_an_inscricao_sends_inscricao_email(): void
    {
        Mail::fake();

        $turma = Turma::create([
            'title' => 'Turma Teste',
            'description' => 'Descrição da turma',
            'checkout_url' => 'https://example.com/checkout',
            'available_slots' => 10,
            'status' => 'aberta',
        ]);

        $response = $this->postJson('/inscricoes', [
            'name' => 'Ciclano',
            'email' => 'ciclano@example.com',
            'phone' => '11988887777',
            'turma_id' => $turma->id,
        ]);

        $response->assertStatus(200);

        Mail::assertSent(EmailInscricao::class, function (EmailInscricao $mail) {
            return $mail->mailTo === 'ciclano@example.com'
                && $mail->subject === 'Inscrição recebida — Missão Nomeação';
        });
    }
}
