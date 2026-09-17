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
            'last_volume_questoes' => 'Volume suficiente',
            'last_percentual_acertos' => 'Muito bom',
            'last_assuntos' => 'Crítico · Abaixo da média',
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
            'last_volume_questoes' => 'Volume alto',
            'last_percentual_acertos' => 'Excelente',
            'last_assuntos' => 'Sem pontos de atenção',
        ]);

        $this->actingAs($user)
            ->get(route('alunos.edit', $aluno))
            ->assertOk()
            ->assertSee('Constância')
            ->assertSee('Quantidade total de questões')
            ->assertSee('Percentual geral de acertos')
            ->assertSee('Percentual por disciplina/assunto')
            ->assertSee('Volume alto')
            ->assertSee('Sem pontos de atenção');
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
