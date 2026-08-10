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
}
