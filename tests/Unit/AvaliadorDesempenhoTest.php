<?php

namespace Tests\Unit;

use App\Services\Desempenho\AvaliadorDesempenho;
use Database\Seeders\ParametrosDesempenhoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvaliadorDesempenhoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ParametrosDesempenhoSeeder::class);
    }

    public function test_constancia_por_dias_falhados(): void
    {
        $svc = new AvaliadorDesempenho;

        $excelente = $svc->avaliarRelatorio([
            'nome' => 'Lara Lacerda',
            'dias_analisados' => 15,
            'dias_estudados' => 15,
            'dias_falhados' => 0,
        ]);
        $this->assertSame('excelente', $excelente['blocos'][0]['faixa'] ?? null);
        $this->assertStringContainsString('excelente', mb_strtolower($excelente['blocos'][0]['texto'] ?? ''));

        $bom = $svc->avaliarRelatorio([
            'nome' => 'Lara',
            'dias_analisados' => 15,
            'dias_estudados' => 13,
            'dias_falhados' => 2,
        ]);
        $this->assertSame('bom', $bom['blocos'][0]['faixa'] ?? null);

        $critico = $svc->avaliarRelatorio([
            'nome' => 'Lara',
            'dias_analisados' => 15,
            'dias_estudados' => 2,
            'dias_falhados' => 13,
        ]);
        $this->assertSame('critico', $critico['blocos'][0]['faixa'] ?? null);
    }

    public function test_volume_e_percentual_so_com_amostra_suficiente(): void
    {
        $svc = new AvaliadorDesempenho;

        $baixo = $svc->avaliarRelatorio([
            'nome' => 'Lara Lacerda',
            'total_questoes' => 20,
            'percentual_acertos' => 95,
        ]);
        $eixos = array_column($baixo['blocos'], 'eixo');
        $this->assertContains('volume_questoes', $eixos);
        $this->assertNotContains('percentual_acertos', $eixos);

        $ok = $svc->avaliarRelatorio([
            'nome' => 'Lara Lacerda',
            'total_questoes' => 324,
            'percentual_acertos' => 78.1,
        ]);
        $eixosOk = array_column($ok['blocos'], 'eixo');
        $this->assertContains('volume_questoes', $eixosOk);
        $this->assertContains('percentual_acertos', $eixosOk);
        $pct = collect($ok['blocos'])->firstWhere('eixo', 'percentual_acertos');
        $this->assertSame('mediano', $pct['faixa'] ?? null);
        $this->assertStringContainsString('Lara', $pct['texto'] ?? '');
    }

    public function test_assuntos_abaixo_da_media(): void
    {
        $svc = new AvaliadorDesempenho;
        $out = $svc->avaliarRelatorio([
            'nome' => 'Lara',
            'assuntos' => [
                ['disciplina' => 'DIR ADM', 'assunto' => 'Improbidade', 'percentual' => 55],
                ['disciplina' => 'DIR ADM', 'assunto' => 'Ato administrativo', 'percentual' => 86],
                ['disciplina' => 'DIR CONST', 'assunto' => 'Poder Judiciário', 'percentual' => 70],
            ],
        ]);

        $assuntos = array_values(array_filter(
            $out['blocos'],
            static fn ($b) => ($b['eixo'] ?? '') === 'assunto'
        ));
        $this->assertCount(2, $assuntos);
        $this->assertSame('critico', $assuntos[0]['faixa']);
        $this->assertSame('abaixo_media', $assuntos[1]['faixa']);
    }
}
