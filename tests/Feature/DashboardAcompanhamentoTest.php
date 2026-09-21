<?php

namespace Tests\Feature;

use App\Enums\AcaoAcompanhamento;
use App\Models\Aluno;
use App\Models\ContatoAluno;
use App\Models\User;
use App\Services\Desempenho\AvaliadorDesempenho;
use Carbon\Carbon;
use Database\Seeders\ParametrosDesempenhoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAcompanhamentoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-22 10:00:00', 'America/Sao_Paulo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_visitante_nao_acessa_o_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_hierarquia_deixa_o_aluno_somente_em_intervir_com_todos_os_motivos(): void
    {
        $user = User::factory()->create(['name' => 'Nayara Oliveira']);
        $aluno = $this->aluno([
            'nome' => 'João Almeida',
            'last_performance' => 'Crítico',
            'last_performance_codigo' => 'critico',
            'last_question_volume' => 'Volume suficiente',
            'last_question_volume_codigo' => 'volume_suficiente',
            'last_accuracy_rate' => 'Crítico',
            'last_accuracy_rate_codigo' => 'critico',
        ]);

        $html = $this->actingAs($user)
            ->get(route('admin.dashboard', ['aluno' => $aluno->id]))
            ->assertOk()
            ->assertSee('Olá, Nayara!')
            ->assertSee('João Almeida')
            ->assertSee('Constância crítica')
            ->assertSee('Desempenho em questões: baixo')
            ->assertSee('data-acao="intervir"', false)
            ->assertDontSee('data-acao="marcar_presenca"', false)
            ->assertDontSee('data-acao="parabenizar"', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'data-acao="intervir"'));
    }

    public function test_evolucao_sem_piora_entra_em_parabenizar(): void
    {
        $user = User::factory()->create();
        $this->aluno([
            'nome' => 'Bruno Silva',
            'last_performance' => 'Bom',
            'last_performance_codigo' => 'bom',
            'prev_performance' => 'Brigando com a constância',
            'prev_performance_codigo' => 'brigando',
            'last_question_volume' => 'Volume suficiente',
            'last_question_volume_codigo' => 'volume_suficiente',
            'prev_question_volume' => 'Volume suficiente',
            'prev_question_volume_codigo' => 'volume_suficiente',
            'last_accuracy_rate' => 'Mediano',
            'last_accuracy_rate_codigo' => 'mediano',
            'prev_accuracy_rate' => 'Mediano',
            'prev_accuracy_rate_codigo' => 'mediano',
        ]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-acao="parabenizar"', false)
            ->assertSee('Constância evoluiu: Brigando com a constância → Bom');
    }

    public function test_assunto_baixo_mostra_a_ponte_com_o_protocolo_de_resgate(): void
    {
        $user = User::factory()->create();
        $aluno = $this->aluno([
            'nome' => 'Maria Costa',
            'assuntos_detalhe' => [[
                'disciplina' => 'Administrativo',
                'assunto' => 'Atos Administrativos',
                'percentual' => 68,
                'faixa' => 'abaixo_media',
                'faixa_nome' => 'Abaixo da média',
            ]],
        ]);

        $this->actingAs($user)
            ->get(route('admin.dashboard', ['aluno' => $aluno->id]))
            ->assertOk()
            ->assertSee('Administrativo · Atos Administrativos: desempenho baixo')
            ->assertSee('Ponte com o Protocolo de Resgate')
            ->assertSee('data-acao="marcar_presenca"', false);
    }

    public function test_registrar_contato_conclui_a_acao_academica(): void
    {
        $user = User::factory()->create();
        $aluno = $this->aluno([
            'nome' => 'Ana Souza',
            'last_accuracy_rate' => 'Alerta',
            'last_accuracy_rate_codigo' => 'alerta',
        ]);

        $this->actingAs($user)
            ->post(route('admin.dashboard.contatos.store', $aluno), [
                'observacao' => 'Combinamos uma revisão de questões.',
            ])
            ->assertRedirect();

        $aluno->refresh();
        $this->assertSame(AcaoAcompanhamento::MarcarPresenca, $aluno->acao_resolvida);
        $this->assertNotNull($aluno->ultimo_contato_em);
        $this->assertSame('Combinamos uma revisão de questões.', $aluno->ultima_observacao);
        $this->assertSame(1, ContatoAluno::query()->count());

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Ana Souza');

        $this->actingAs($user)
            ->get(route('admin.dashboard', ['situacao' => 'concluidas', 'aluno' => $aluno->id]))
            ->assertOk()
            ->assertSee('Ana Souza')
            ->assertSee('Combinamos uma revisão de questões.')
            ->assertSee('Concluída');
    }

    public function test_mais_de_quinze_dias_sem_contato_entra_em_marcar_presenca(): void
    {
        $user = User::factory()->create();
        $this->aluno([
            'nome' => 'Luíza Alves',
            'created_at' => Carbon::parse('2026-09-01 09:00:00'),
        ]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Luíza Alves')
            ->assertSee('21 dias sem contato')
            ->assertSee('data-acao="marcar_presenca"', false);
    }

    public function test_agendar_para_hoje_entra_na_lista_do_dia(): void
    {
        $user = User::factory()->create();
        $aluno = $this->aluno(['nome' => 'Pedro Lima']);

        $this->actingAs($user)
            ->post(route('admin.dashboard.agenda.store', $aluno), [
                'proximo_contato_em' => '2026-09-22',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('admin.dashboard', ['foco' => 'agenda_hoje']))
            ->assertOk()
            ->assertSee('Pedro Lima')
            ->assertSee('Contato programado para hoje');
    }

    public function test_periodo_novo_guarda_a_faixa_anterior_e_reabre_a_acao(): void
    {
        $this->seed(ParametrosDesempenhoSeeder::class);
        $avaliador = new AvaliadorDesempenho;
        $aluno = $this->aluno([
            'nome' => 'Carla Mendes',
            'acao_resolvida' => AcaoAcompanhamento::Intervir->value,
            'acao_resolvida_assinatura' => 'assinatura-antiga',
        ]);

        $aluno->aplicarAvaliacaoDesempenho($avaliador->avaliarRelatorio([
            'nome' => $aluno->nome,
            'dias_analisados' => 15,
            'dias_estudados' => 2,
            'dias_falhados' => 13,
            'total_questoes' => 40,
            'percentual_acertos' => 40,
        ]), '2026-09-1');

        $aluno->refresh();
        $this->assertSame('Crítico', $aluno->last_performance);
        $this->assertSame('critico', $aluno->last_performance_codigo);
        $this->assertNull($aluno->prev_performance);
        $this->assertNull($aluno->acao_resolvida);

        $aluno->aplicarAvaliacaoDesempenho($avaliador->avaliarRelatorio([
            'nome' => $aluno->nome,
            'dias_analisados' => 15,
            'dias_estudados' => 12,
            'dias_falhados' => 3,
            'total_questoes' => 180,
            'percentual_acertos' => 82,
            'assuntos' => [
                ['disciplina' => 'DIR ADM', 'assunto' => 'Atos Administrativos', 'percentual' => 58],
            ],
        ]), '2026-09-2');

        $aluno->refresh();
        $this->assertSame('Crítico', $aluno->prev_performance);
        $this->assertSame('Bom', $aluno->last_performance);
        $this->assertSame('Crítico e inconclusivo', $aluno->prev_question_volume);
        $this->assertSame('Volume suficiente', $aluno->last_question_volume);
        $this->assertSame('Muito bom', $aluno->last_accuracy_rate);
        $this->assertSame('Atos Administrativos', $aluno->assuntos_detalhe[0]['assunto'] ?? null);
        $this->assertSame('critico', $aluno->assuntos_detalhe[0]['faixa'] ?? null);

        $aluno->aplicarAvaliacaoDesempenho($avaliador->avaliarRelatorio([
            'nome' => $aluno->nome,
            'dias_analisados' => 15,
            'dias_estudados' => 14,
            'dias_falhados' => 1,
            'total_questoes' => 220,
            'percentual_acertos' => 88,
        ]), '2026-09-2');

        $aluno->refresh();
        $this->assertSame('Crítico', $aluno->prev_performance);
        $this->assertSame('Bom', $aluno->last_performance);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function aluno(array $dados): Aluno
    {
        $criadoEm = $dados['created_at'] ?? Carbon::parse('2026-09-20 09:00:00');
        unset($dados['created_at'], $dados['updated_at']);

        $aluno = Aluno::create(array_merge([
            'nome' => 'Aluno Teste',
            'email' => fake()->unique()->safeEmail(),
            'recebe_email' => false,
        ], $dados));
        $aluno->forceFill([
            'created_at' => $criadoEm,
            'updated_at' => $criadoEm,
        ])->save();

        return $aluno->fresh();
    }
}
