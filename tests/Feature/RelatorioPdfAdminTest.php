<?php

namespace Tests\Feature;

use App\Models\Configuracao;
use App\Models\User;
use App\Services\Tutory\PdfFontes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatorioPdfAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_nao_acessa_configuracao_do_pdf(): void
    {
        $this->get(route('relatorios-pdf.index'))->assertRedirect(route('login'));
        $this->get(route('relatorios-pdf.preview'))->assertRedirect(route('login'));
    }

    public function test_admin_ve_pagina_e_preview_pdf(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('relatorios-pdf.index'))
            ->assertOk()
            ->assertSee('PDF do relatório')
            ->assertSee('Inter')
            ->assertSee('DejaVu Sans')
            ->assertSee('Helvetica');

        $preview = $this->actingAs($user)
            ->get(route('relatorios-pdf.preview', ['fonte' => 'dejavu']));

        $preview->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $preview->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $preview->getContent());
    }

    public function test_admin_salva_fonte_do_pdf(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('relatorios-pdf.update'), ['fonte' => 'helvetica'])
            ->assertRedirect(route('relatorios-pdf.index'));

        $this->assertSame('helvetica', Configuracao::valor(PdfFontes::CONFIG_CHAVE));
        $this->assertSame('helvetica', PdfFontes::chaveAtual());
        $this->assertSame('Helvetica', PdfFontes::familiaDompdf());
    }

    public function test_fonte_invalida_e_rejeitada(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('relatorios-pdf.index'))
            ->put(route('relatorios-pdf.update'), ['fonte' => 'comic-sans'])
            ->assertRedirect(route('relatorios-pdf.index'))
            ->assertSessionHasErrors('fonte');
    }
}
