<?php

namespace Tests\Feature;

use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TurmaAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function visitante_nao_acessa_o_cadastro_de_turmas(): void
    {
        $this->get(route('turmas.index'))->assertRedirect(route('login'));
        $this->get(route('turmas.create'))->assertRedirect(route('login'));
    }

    #[Test]
    public function admin_cria_turma_com_parametros_do_site(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('turmas.store'), [
                'title' => 'PRF Policial 2026',
                'nome_publico' => 'PRF — Policial',
                'description' => 'Preparação para a PRF.',
                'grupo_exibicao' => Turma::GRUPO_TURMA_DIRECIONADA,
                'categoria_navegacao' => 'Policiais',
                'orgao' => 'PRF',
                'cargo' => 'Policial',
                'termos_busca' => 'PRF, Polícia Rodoviária Federal',
                'momento_concurso' => 'previsto',
                'exibir_momento_concurso' => '1',
                'acao_principal' => Turma::ACAO_CHECKOUT,
                'checkout_url' => 'https://checkout.example.com/prf',
                'ativo' => '1',
                'exibir_no_site' => '1',
                'aceitar_novos_alunos' => '1',
                'destacar_turmas_abertas' => '1',
                'exibir_na_mentoria' => '1',
                'ordem_exibicao' => 4,
                'secao_pagina' => 'Já tem um concurso ou carreira como alvo?',
                'texto_cta' => 'COMEÇAR AGORA',
                'popup_opcoes' => [
                    ['label' => 'Agente Administrativo', 'url' => 'https://checkout.example.com/adm'],
                    ['label' => '', 'url' => ''],
                ],
            ])
            ->assertRedirect(route('turmas.index'));

        $this->assertDatabaseHas('turmas', [
            'title' => 'PRF Policial 2026',
            'nome_publico' => 'PRF — Policial',
            'slug' => 'prf-policial-2026',
            'orgao' => 'PRF',
            'acao_principal' => 'checkout',
            'exibir_na_mentoria' => 1,
            'destacar_turmas_abertas' => 1,
        ]);

        $turma = Turma::where('slug', 'prf-policial-2026')->first();
        $this->assertCount(1, $turma->popupOpcoesNormalizadas());
        $this->assertSame('INSCRIÇÕES ABERTAS', $turma->badgePublico());
    }

    #[Test]
    public function admin_atualiza_visibilidade_e_estagio(): void
    {
        $user = User::factory()->create();
        $turma = Turma::factory()->create([
            'title' => 'ABIN 2026',
            'slug' => 'abin',
            'aceitar_novos_alunos' => true,
        ]);

        $this->actingAs($user)
            ->put(route('turmas.update', $turma), [
                'title' => 'ABIN 2026',
                'slug' => 'abin',
                'nome_publico' => 'ABIN',
                'grupo_exibicao' => Turma::GRUPO_TURMA_DIRECIONADA,
                'acao_principal' => Turma::ACAO_WHATSAPP,
                'whatsapp_url' => 'https://wa.me/5561999999999',
                'ativo' => '1',
                'exibir_no_site' => '1',
                'ordem_exibicao' => 2,
            ])
            ->assertRedirect(route('turmas.index'));

        $turma->refresh();
        $this->assertFalse($turma->aceitar_novos_alunos);
        $this->assertSame('EM BREVE', $turma->badgePublico());
        $this->assertSame('fechada', $turma->status);
    }
}
