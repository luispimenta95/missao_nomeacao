<?php

namespace Tests\Unit;

use App\Models\CriterioDesempenho;
use App\Models\NivelDesempenho;
use App\Models\RegraDesempenho;
use App\Services\Desempenho\AvaliadorDesempenho;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvaliadorDesempenhoTest extends TestCase
{
    use RefreshDatabase;

    public function test_converte_horas_e_percentual(): void
    {
        $svc = new AvaliadorDesempenho;

        $this->assertSame(693.75, $svc->paraNumero('693:45'));
        $this->assertSame(84.0, $svc->paraNumero('84%'));
        $this->assertSame(140.0, $svc->paraNumero('140'));
    }

    public function test_avalia_nivel_com_um_e_varios_criterios(): void
    {
        $this->seed(\Database\Seeders\CriteriosDesempenhoSeeder::class);

        $pct = CriterioDesempenho::query()->where('codigo', 'pct_questoes')->firstOrFail();
        $dias = CriterioDesempenho::query()->where('codigo', 'dias')->firstOrFail();

        $excelente = NivelDesempenho::create([
            'nome' => 'Excelente',
            'slug' => 'excelente',
            'ordem' => 1,
            'texto_email' => 'Seu desempenho foi excelente.',
            'ativo' => true,
        ]);
        RegraDesempenho::create([
            'nivel_desempenho_id' => $excelente->id,
            'criterio_desempenho_id' => $pct->id,
            'operador' => '>=',
            'valor_min' => 80,
        ]);
        RegraDesempenho::create([
            'nivel_desempenho_id' => $excelente->id,
            'criterio_desempenho_id' => $dias->id,
            'operador' => '>=',
            'valor_min' => 10,
        ]);

        $regular = NivelDesempenho::create([
            'nome' => 'Regular',
            'slug' => 'regular',
            'ordem' => 2,
            'texto_email' => 'Desempenho regular.',
            'ativo' => true,
        ]);
        RegraDesempenho::create([
            'nivel_desempenho_id' => $regular->id,
            'criterio_desempenho_id' => $pct->id,
            'operador' => '>=',
            'valor_min' => 50,
        ]);

        $svc = new AvaliadorDesempenho;
        $ok = $svc->avaliar(['pct_questoes' => 84, 'dias' => 140]);
        $this->assertSame('Excelente', $ok['nivel']?->nome);

        $mid = $svc->avaliar(['pct_questoes' => 60, 'dias' => 5]);
        $this->assertSame('Regular', $mid['nivel']?->nome);

        $none = $svc->avaliar(['pct_questoes' => 40, 'dias' => 5]);
        $this->assertNull($none['nivel']);
    }
}
