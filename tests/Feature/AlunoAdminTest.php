<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlunoAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_nao_cria_aluno_com_nome_duplicado(): void
    {
        $user = User::factory()->create();
        Aluno::create([
            'nome' => 'Giovanna',
            'email' => 'giovanna@example.com',
            'recebe_email' => true,
        ]);

        $this->actingAs($user)
            ->from(route('alunos.create'))
            ->post(route('alunos.store'), [
                'nome' => 'Giovanna',
                'email' => 'outra@example.com',
                'recebe_email' => '1',
            ])
            ->assertRedirect(route('alunos.create'))
            ->assertSessionHasErrors('nome');

        $this->assertSame(1, Aluno::query()->count());
    }

    public function test_lista_mostra_as_faixas_de_desempenho_do_aluno(): void
    {
        $user = User::factory()->create();
        Aluno::create([
            'nome' => 'Giovanna',
            'email' => 'giovanna@example.com',
            'recebe_email' => true,
            'last_performance' => 'Brigando com a constância',
            'last_question_volume' => 'Volume suficiente',
            'last_accuracy_rate' => 'Muito bom',
            'last_subjects' => 'Crítico · Abaixo da média',
        ]);

        $this->actingAs($user)
            ->get(route('alunos.index'))
            ->assertOk()
            ->assertSee('Constância')
            ->assertSee('Questões')
            ->assertSee('% acertos')
            ->assertSee('Assuntos')
            ->assertSee('Brigando com a constância')
            ->assertSee('Volume suficiente')
            ->assertSee('Muito bom')
            ->assertSee('Crítico · Abaixo da média');
    }

    public function test_edicao_mostra_as_faixas_somente_leitura(): void
    {
        $user = User::factory()->create();
        $aluno = Aluno::create([
            'nome' => 'Giovanna',
            'email' => 'giovanna@example.com',
            'recebe_email' => true,
            'last_performance' => 'Excelente',
            'last_question_volume' => 'Volume alto',
            'last_accuracy_rate' => 'Excelente',
            'last_subjects' => 'Sem pontos de atenção',
        ]);

        $html = $this->actingAs($user)
            ->get(route('alunos.edit', $aluno))
            ->assertOk()
            ->assertSee('Constância')
            ->assertSee('Quantidade total de questões')
            ->assertSee('Percentual geral de acertos')
            ->assertSee('Percentual por disciplina/assunto')
            ->assertSee('Volume alto')
            ->assertSee('Sem pontos de atenção')
            ->getContent();

        $this->assertStringNotContainsString('name="last_performance"', $html);
        $this->assertStringNotContainsString('name="last_question_volume"', $html);
        $this->assertStringNotContainsString('name="last_accuracy_rate"', $html);
        $this->assertStringNotContainsString('name="last_subjects"', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*(readonly|last_question_volume|Volume alto)/i', $html);
    }

    public function test_lista_tem_busca_por_nome(): void
    {
        $user = User::factory()->create();
        Aluno::create([
            'nome' => 'Giovanna Silva',
            'email' => 'giovanna@example.com',
            'recebe_email' => true,
        ]);
        Aluno::create([
            'nome' => 'Maria Souza',
            'email' => 'maria@example.com',
            'recebe_email' => false,
        ]);

        $html = $this->actingAs($user)
            ->get(route('alunos.index'))
            ->assertOk()
            ->assertSee('Buscar por nome')
            ->assertSee('busca-aluno')
            ->assertSee('Giovanna Silva')
            ->assertSee('Maria Souza')
            ->getContent();

        $this->assertStringContainsString("addEventListener('input'", $html);
        $this->assertStringContainsString('XMLHttpRequest', $html);
    }

    public function test_filtra_alunos_por_nome_com_like(): void
    {
        $user = User::factory()->create();
        Aluno::create([
            'nome' => 'Giovanna Silva',
            'email' => 'giovanna@example.com',
            'recebe_email' => true,
        ]);
        Aluno::create([
            'nome' => 'Maria Souza',
            'email' => 'maria@example.com',
            'recebe_email' => false,
        ]);

        $this->actingAs($user)
            ->get(route('alunos.index', ['busca' => 'vann']))
            ->assertOk()
            ->assertSee('Giovanna Silva')
            ->assertDontSee('Maria Souza')
            ->assertSee('value="vann"', false);

        $this->actingAs($user)
            ->get(route('alunos.index', ['busca' => 'maria']))
            ->assertOk()
            ->assertSee('Maria Souza')
            ->assertDontSee('Giovanna Silva');
    }

    public function test_busca_em_tempo_real_devolve_so_as_linhas(): void
    {
        $user = User::factory()->create();
        Aluno::create([
            'nome' => 'Ana Lima',
            'email' => 'ana@example.com',
            'recebe_email' => true,
        ]);
        Aluno::create([
            'nome' => 'Bruno Costa',
            'email' => 'bruno@example.com',
            'recebe_email' => true,
        ]);

        $this->actingAs($user)
            ->get(route('alunos.index', ['busca' => 'Ana']), [
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->assertSee('Ana Lima')
            ->assertDontSee('Bruno Costa')
            ->assertDontSee('Gerenciar Alunos');

        $this->actingAs($user)
            ->get(route('alunos.index', ['busca' => 'zzz']), [
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->assertSee('Nenhum aluno encontrado para essa busca.')
            ->assertDontSee('Ana Lima');
    }

    public function test_coringa_like_nao_lista_todo_mundo(): void
    {
        $user = User::factory()->create();
        Aluno::create([
            'nome' => 'Giovanna Silva',
            'email' => 'giovanna@example.com',
            'recebe_email' => true,
        ]);

        $this->actingAs($user)
            ->get(route('alunos.index', ['busca' => '%']))
            ->assertOk()
            ->assertDontSee('Giovanna Silva')
            ->assertSee('Nenhum aluno encontrado para essa busca.');
    }

    public function test_comando_de_sincronizacao_esta_agendado_as_6h(): void
    {
        $src = (string) file_get_contents(base_path('routes/console.php'));
        $this->assertStringContainsString("Schedule::command('tutory:sincronizar-alunos')", $src);
        $this->assertStringContainsString("->monthlyOn(1, '06:00')", $src);
        $this->assertStringContainsString("->monthlyOn(16, '06:00')", $src);
        $this->assertStringContainsString("->monthlyOn(1, '10:30')", $src);
        $this->assertStringContainsString("->monthlyOn(16, '10:30')", $src);
        $this->assertStringContainsString('--se-pendente', $src);
        $this->assertStringContainsString("->timezone('America/Sao_Paulo')", $src);
    }
}
