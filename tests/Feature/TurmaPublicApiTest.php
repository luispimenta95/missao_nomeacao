<?php

namespace Tests\Feature;

use App\Models\Turma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TurmaPublicApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function api_lista_apenas_turmas_ativas_e_visiveis_no_site(): void
    {
        $aberta = Turma::factory()->create([
            'slug' => 'prf-policial',
            'nome_publico' => 'PRF — Policial',
            'destacar_turmas_abertas' => true,
            'exibir_na_mentoria' => true,
            'ordem_exibicao' => 1,
        ]);
        Turma::factory()->arquivada()->create(['slug' => 'arquivo']);
        Turma::factory()->ocultadaDoSite()->create(['slug' => 'oculta']);
        $mentoria = Turma::factory()->create([
            'slug' => 'carreiras-policiais',
            'nome_publico' => 'Carreiras Policiais',
            'exibir_na_mentoria' => true,
            'destacar_turmas_abertas' => false,
            'ordem_exibicao' => 2,
        ]);

        $turmasAbertas = $this->getJson('/api/turmas?pagina=turmas-abertas');
        $turmasAbertas->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', $aberta->slug)
            ->assertJsonPath('data.0.nome_publico', 'PRF — Policial')
            ->assertJsonPath('data.0.badge', 'INSCRIÇÕES ABERTAS')
            ->assertJsonPath('data.0.texto_cta', 'COMEÇAR AGORA');

        $mentoriaResp = $this->getJson('/api/turmas?pagina=mentoria');
        $mentoriaResp->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.slug', $mentoria->slug);

        $destacadas = $this->getJson('/api/turmas?destacadas=1');
        $destacadas->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function api_nao_expone_turma_inativa_por_slug(): void
    {
        Turma::factory()->arquivada()->create(['slug' => 'arquivo']);

        $this->getJson('/api/turmas/arquivo')->assertNotFound();
    }

    #[Test]
    public function api_mostra_detalhe_publico_com_popup(): void
    {
        Turma::factory()->create([
            'slug' => 'analista-tribunais',
            'nome_publico' => 'Analista Judiciário — Tribunais',
            'popup_opcoes' => [
                ['label' => 'Analista', 'url' => 'https://checkout.example.com/analista'],
                ['label' => 'Técnico', 'url' => 'https://checkout.example.com/tecnico'],
            ],
            'exibir_momento_concurso' => true,
            'momento_concurso' => 'edital_publicado',
        ]);

        $this->getJson('/api/turmas/analista-tribunais')
            ->assertOk()
            ->assertJsonPath('data.nome_publico', 'Analista Judiciário — Tribunais')
            ->assertJsonPath('data.momento_concurso', 'EDITAL PUBLICADO')
            ->assertJsonCount(2, 'data.popup_opcoes');
    }

    #[Test]
    public function inscricao_e_bloqueada_quando_nao_aceita_novos_alunos(): void
    {
        $turma = Turma::factory()->emBreve()->create();

        $this->postJson('/inscricoes', [
            'name' => 'Ciclano',
            'email' => 'ciclano@example.com',
            'phone' => '11988887777',
            'turma_id' => $turma->id,
        ])->assertStatus(400)
            ->assertJsonPath('success', false);
    }
}
