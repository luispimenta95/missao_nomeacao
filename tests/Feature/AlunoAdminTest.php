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

    public function test_relatorio_ordena_por_status_e_depois_por_nome(): void
    {
        $user = User::factory()->create();
        Aluno::create([
            'nome' => 'Ana Inativa',
            'email' => 'ana@example.com',
            'recebe_email' => true,
            'ativo' => false,
        ]);
        Aluno::create([
            'nome' => 'Zeca Ativo',
            'email' => 'zeca@example.com',
            'recebe_email' => true,
            'ativo' => true,
        ]);
        Aluno::create([
            'nome' => 'Bruno Ativo',
            'email' => 'bruno@example.com',
            'recebe_email' => true,
            'ativo' => true,
        ]);

        $html = $this->actingAs($user)->get(route('alunos.index'))->assertOk()->getContent();
        $posBruno = strpos($html, 'Bruno Ativo');
        $posZeca = strpos($html, 'Zeca Ativo');
        $posAna = strpos($html, 'Ana Inativa');
        $this->assertNotFalse($posBruno);
        $this->assertNotFalse($posZeca);
        $this->assertNotFalse($posAna);
        $this->assertTrue($posBruno < $posZeca && $posZeca < $posAna);

        $linhas = $this->linhasCsv((string) $this->actingAs($user)->get(route('alunos.export'))->getContent());
        $this->assertSame('Bruno Ativo', $linhas[1][0]);
        $this->assertSame('Ativo', $linhas[1][3]);
        $this->assertSame('Zeca Ativo', $linhas[2][0]);
        $this->assertSame('Ana Inativa', $linhas[3][0]);
        $this->assertSame('Inativo', $linhas[3][3]);
    }

    public function test_exporta_alunos_em_csv(): void
    {
        $user = User::factory()->create();
        Aluno::create([
            'nome' => 'Giovanna "Silva", Jr.',
            'email' => 'giovanna@example.com',
            'recebe_email' => true,
            'last_performance' => 'Brigando com a constância',
            'last_question_volume' => 'Volume suficiente',
            'last_accuracy_rate' => 'Muito bom',
            'last_subjects' => 'Crítico · Abaixo da média',
        ]);
        Aluno::create([
            'nome' => 'Maria Souza',
            'email' => 'maria@example.com',
            'recebe_email' => false,
        ]);

        $this->get(route('alunos.export'))->assertRedirect(route('login'));

        $resposta = $this->actingAs($user)
            ->get(route('alunos.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=utf-8');

        $disposicao = (string) $resposta->headers->get('content-disposition');
        $this->assertMatchesRegularExpression('/filename=alunos_\d{4}-\d{2}-\d{2}_\d{6}\.csv/', $disposicao);

        $linhas = $this->linhasCsv((string) $resposta->getContent());
        $this->assertSame(
            ['Nome', 'E-mail', 'Recebe e-mail', 'Status', 'Constância', 'Questões', '% acertos', 'Assuntos'],
            $linhas[0]
        );
        $this->assertSame([
            'Giovanna "Silva", Jr.',
            'giovanna@example.com',
            'Sim',
            'Ativo',
            'Brigando com a constância',
            'Volume suficiente',
            'Muito bom',
            'Crítico · Abaixo da média',
        ], $linhas[1]);
        $this->assertSame([
            'Maria Souza',
            'maria@example.com',
            'Não',
            'Ativo',
            '',
            '',
            '',
            '',
        ], $linhas[2]);
    }

    public function test_exportacao_csv_respeita_a_busca_por_nome(): void
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

        $resposta = $this->actingAs($user)
            ->get(route('alunos.export', ['busca' => 'vann']))
            ->assertOk();

        $linhas = $this->linhasCsv((string) $resposta->getContent());
        $this->assertCount(2, $linhas);
        $this->assertSame('Giovanna Silva', $linhas[1][0]);

        $html = $this->actingAs($user)
            ->get(route('alunos.index', ['busca' => 'vann']))
            ->assertOk()
            ->assertSee('Exportar CSV')
            ->getContent();

        $this->assertStringContainsString(route('alunos.export', ['busca' => 'vann']), $html);
        $this->assertStringContainsString('exportar-alunos-csv', $html);
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

    /**
     * @return list<list<string|null>>
     */
    private function linhasCsv(string $csv): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $linhas = [];
        while (($linha = fgetcsv($handle)) !== false) {
            $linhas[] = $linha;
        }
        fclose($handle);

        return $linhas;
    }
}
