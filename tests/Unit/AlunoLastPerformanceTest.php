<?php

namespace Tests\Unit;

use App\Models\Aluno;
use App\Services\Desempenho\AvaliadorDesempenho;
use Database\Seeders\ParametrosDesempenhoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlunoLastPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ParametrosDesempenhoSeeder::class);
    }

    public function test_last_performance_recebe_resumo_em_portugues(): void
    {
        $aluno = Aluno::create([
            'nome' => 'Lara Lacerda',
            'email' => 'lara@example.com',
            'recebe_email' => false,
        ]);

        $avaliacao = (new AvaliadorDesempenho)->avaliarRelatorio([
            'nome' => $aluno->nome,
            'dias_analisados' => 15,
            'dias_estudados' => 15,
            'dias_falhados' => 0,
        ]);

        $resumo = $avaliacao['resumo'] ?? null;
        $this->assertNotNull($resumo);
        $this->assertSame('Excelente', $resumo);

        $aluno->last_performance = $resumo;
        $aluno->save();

        $this->assertSame('Excelente', $aluno->fresh()->last_performance);
    }

    public function test_last_performance_e_fillable(): void
    {
        $aluno = Aluno::create([
            'nome' => 'Ana',
            'email' => 'ana@example.com',
            'recebe_email' => true,
            'last_performance' => 'Bom',
        ]);

        $this->assertSame('Bom', $aluno->fresh()->last_performance);
    }

    public function test_grava_todas_as_faixas_do_ultimo_relatorio(): void
    {
        $aluno = Aluno::create([
            'nome' => 'Lara Lacerda',
            'email' => 'lara@example.com',
            'recebe_email' => false,
        ]);

        $avaliacao = (new AvaliadorDesempenho)->avaliarRelatorio([
            'nome' => $aluno->nome,
            'dias_analisados' => 15,
            'dias_estudados' => 6,
            'dias_falhados' => 9,
            'total_questoes' => 306,
            'percentual_acertos' => 83.7,
            'assuntos' => [
                ['disciplina' => 'DIR CONST', 'assunto' => 'Poder Judiciário', 'percentual' => 55],
                ['disciplina' => 'DIR ADM', 'assunto' => 'Ato administrativo', 'percentual' => 70],
            ],
        ]);

        $aluno->aplicarAvaliacaoDesempenho($avaliacao);
        $aluno = $aluno->fresh();

        $this->assertSame('Brigando com a constância', $aluno->last_performance);
        $this->assertSame('Volume suficiente', $aluno->last_question_volume);
        $this->assertSame('Muito bom', $aluno->last_accuracy_rate);
        $this->assertSame('Crítico · Abaixo da média', $aluno->last_subjects);
    }

    public function test_percentual_fica_vazio_quando_amostra_e_insuficiente(): void
    {
        $aluno = Aluno::create([
            'nome' => 'Ana',
            'email' => 'ana@example.com',
            'recebe_email' => true,
            'last_accuracy_rate' => 'Excelente',
        ]);

        $avaliacao = (new AvaliadorDesempenho)->avaliarRelatorio([
            'nome' => $aluno->nome,
            'total_questoes' => 40,
            'percentual_acertos' => 95,
            'assuntos' => [
                ['disciplina' => 'DIR ADM', 'assunto' => 'Licitações', 'percentual' => 90],
            ],
        ]);

        $aluno->aplicarAvaliacaoDesempenho($avaliacao);
        $aluno = $aluno->fresh();

        $this->assertSame('Crítico e inconclusivo', $aluno->last_question_volume);
        $this->assertNull($aluno->last_accuracy_rate);
        $this->assertSame('Sem pontos de atenção', $aluno->last_subjects);
    }
}
