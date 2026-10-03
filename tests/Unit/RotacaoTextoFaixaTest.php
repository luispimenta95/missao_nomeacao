<?php

namespace Tests\Unit;

use App\Models\Aluno;
use App\Models\EixoDesempenho;
use App\Models\FaixaDesempenho;
use App\Models\UsoTextoDesempenho;
use App\Services\Desempenho\AvaliadorDesempenho;
use App\Services\Desempenho\RegistroInicialTextoFaixa;
use Database\Seeders\ParametrosDesempenhoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RotacaoTextoFaixaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ParametrosDesempenhoSeeder::class);
    }

    public function test_seed_mantem_o_texto_atual_e_marca_como_ultimo_uso(): void
    {
        $constancia = $this->faixa(EixoDesempenho::CONSTANCIA, 'critico');
        $assunto = $this->faixa(EixoDesempenho::ASSUNTO, 'critico');
        $canonicoConstancia = $constancia->textos()->where('canonico', true)->firstOrFail();
        $this->assertSame($constancia->texto_email, $canonicoConstancia->texto);

        $aluno = $this->aluno([
            'last_performance' => 'Crítico',
            'last_performance_codigo' => null,
            'metricas_periodo' => '2026-09-2',
            'assuntos_detalhe' => [[
                'disciplina' => 'Direito',
                'assunto' => 'Aplicabilidade',
                'percentual' => 60,
                'faixa' => 'critico',
                'faixa_nome' => 'Protocolo de Resgate - Urgência',
            ]],
        ]);

        $gravados = (new RegistroInicialTextoFaixa)->registrar($aluno);
        $this->assertSame(2, $gravados);
        $this->assertSame(0, (new RegistroInicialTextoFaixa)->registrar($aluno->fresh()));

        $this->assertDatabaseHas('usos_texto_desempenho', [
            'aluno_id' => $aluno->id,
            'faixa_desempenho_id' => $constancia->id,
            'texto_faixa_desempenho_id' => $canonicoConstancia->id,
            'periodo' => '2026-09-2',
            'ciclo' => 1,
        ]);
        $this->assertDatabaseHas('usos_texto_desempenho', [
            'aluno_id' => $aluno->id,
            'faixa_desempenho_id' => $assunto->id,
            'texto_faixa_desempenho_id' => $assunto->textos()->where('canonico', true)->value('id'),
        ]);
    }

    public function test_mesma_faixa_usa_o_proximo_texto_e_repete_o_periodo(): void
    {
        $faixa = $this->faixa(EixoDesempenho::CONSTANCIA, 'critico');
        $alternativo = $faixa->textos()->create([
            'texto' => 'Segundo texto da constância crítica para {NOME}.',
            'ordem' => 2,
            'canonico' => false,
            'ativo' => true,
        ]);
        $aluno = $this->aluno([
            'last_performance' => 'Crítico',
            'last_performance_codigo' => 'critico',
            'metricas_periodo' => '2026-09-2',
        ]);
        (new RegistroInicialTextoFaixa)->registrar($aluno);

        $avaliador = new AvaliadorDesempenho;
        $dados = [
            'nome' => 'Lara Lacerda',
            'dias_analisados' => 15,
            'dias_estudados' => 2,
            'dias_falhados' => 13,
        ];

        $repetido = $avaliador->avaliarRelatorio($dados, $aluno, '2026-09-2');
        $this->assertSame(
            $faixa->textos()->where('canonico', true)->value('id'),
            $this->bloco($repetido, 'constancia')['texto_faixa_id'] ?? null
        );
        $this->assertStringContainsString('13 dias sem estudar', $this->bloco($repetido, 'constancia')['texto'] ?? '');
        $this->assertSame(1, UsoTextoDesempenho::query()->where('aluno_id', $aluno->id)->where('faixa_desempenho_id', $faixa->id)->count());

        $seguinte = $avaliador->avaliarRelatorio($dados, $aluno, '2026-10-1');
        $bloco = $this->bloco($seguinte, 'constancia');
        $this->assertSame($alternativo->id, $bloco['texto_faixa_id'] ?? null);
        $this->assertStringContainsString('Segundo texto', $bloco['texto'] ?? '');
        $this->assertStringContainsString('Lara', $bloco['texto'] ?? '');

        $deNovo = $avaliador->avaliarRelatorio($dados, $aluno, '2026-10-1');
        $this->assertSame($alternativo->id, $this->bloco($deNovo, 'constancia')['texto_faixa_id'] ?? null);
        $this->assertSame(2, UsoTextoDesempenho::query()->where('aluno_id', $aluno->id)->where('faixa_desempenho_id', $faixa->id)->count());
    }

    public function test_faixa_diferente_nao_consulta_o_historico_e_esgota_a_lista(): void
    {
        $critico = $this->faixa(EixoDesempenho::CONSTANCIA, 'critico');
        $alternativo = $critico->textos()->create([
            'texto' => 'Texto alternativo que não deve entrar quando a faixa muda.',
            'ordem' => 2,
            'canonico' => false,
            'ativo' => true,
        ]);
        $volumeSuficiente = $this->faixa(EixoDesempenho::VOLUME_QUESTOES, 'volume_suficiente');
        $volumeSuficiente->textos()->create([
            'texto' => 'Volume suficiente, texto dois, {TOTAL_QUESTOES} questões.',
            'ordem' => 2,
            'canonico' => false,
            'ativo' => true,
        ]);

        $aluno = $this->aluno([
            'last_performance' => 'Excelente',
            'last_performance_codigo' => 'excelente',
            'last_question_volume' => 'Volume baixo',
            'last_question_volume_codigo' => 'volume_baixo',
            'metricas_periodo' => '2026-09-2',
        ]);
        (new RegistroInicialTextoFaixa)->registrar($aluno);
        UsoTextoDesempenho::query()->create([
            'aluno_id' => $aluno->id,
            'faixa_desempenho_id' => $critico->id,
            'texto_faixa_desempenho_id' => $alternativo->id,
            'eixo_codigo' => EixoDesempenho::CONSTANCIA,
            'ciclo' => 1,
            'periodo' => '2026-08-2',
        ]);

        $avaliador = new AvaliadorDesempenho;
        $dados = [
            'nome' => 'Lara',
            'dias_analisados' => 15,
            'dias_estudados' => 2,
            'dias_falhados' => 13,
            'total_questoes' => 324,
        ];
        $mudou = $avaliador->avaliarRelatorio($dados, $aluno, '2026-10-1');

        $this->assertSame(
            $critico->textos()->where('canonico', true)->value('id'),
            $this->bloco($mudou, 'constancia')['texto_faixa_id'] ?? null
        );
        $this->assertSame(
            $volumeSuficiente->textos()->where('canonico', true)->value('id'),
            $this->bloco($mudou, 'volume_questoes')['texto_faixa_id'] ?? null
        );

        $aluno->last_performance = 'Crítico';
        $aluno->last_performance_codigo = 'critico';
        $aluno->last_question_volume = 'Volume suficiente';
        $aluno->last_question_volume_codigo = 'volume_suficiente';
        $aluno->save();

        $manteve = $avaliador->avaliarRelatorio($dados, $aluno->fresh(), '2026-10-2');
        $this->assertSame($alternativo->id, $this->bloco($manteve, 'constancia')['texto_faixa_id'] ?? null);
        $this->assertStringContainsString('texto dois', $this->bloco($manteve, 'volume_questoes')['texto'] ?? '');

        $esgotou = $avaliador->avaliarRelatorio($dados, $aluno->fresh(), '2026-11-1');
        $this->assertSame(
            $critico->textos()->where('canonico', true)->value('id'),
            $this->bloco($esgotou, 'constancia')['texto_faixa_id'] ?? null
        );
        $this->assertSame(
            3,
            (int) UsoTextoDesempenho::query()
                ->where('aluno_id', $aluno->id)
                ->where('faixa_desempenho_id', $critico->id)
                ->where('texto_faixa_desempenho_id', $critico->textos()->where('canonico', true)->value('id'))
                ->max('ciclo')
        );
    }

    public function test_sem_aluno_mantem_o_texto_principal_e_nao_grava_uso(): void
    {
        $faixa = $this->faixa(EixoDesempenho::CONSTANCIA, 'excelente');
        $faixa->textos()->create([
            'texto' => 'Não usar sem aluno.',
            'ordem' => 2,
            'canonico' => false,
            'ativo' => true,
        ]);

        $out = (new AvaliadorDesempenho)->avaliarRelatorio([
            'nome' => 'Lara Lacerda',
            'dias_analisados' => 15,
            'dias_estudados' => 15,
            'dias_falhados' => 0,
        ]);

        $this->assertStringContainsString('excelente', mb_strtolower($this->bloco($out, 'constancia')['texto'] ?? ''));
        $this->assertSame(0, UsoTextoDesempenho::query()->count());
    }

    private function faixa(string $eixo, string $codigo): FaixaDesempenho
    {
        return FaixaDesempenho::query()
            ->where('codigo', $codigo)
            ->whereHas('eixo', static fn ($q) => $q->where('codigo', $eixo))
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function aluno(array $extra): Aluno
    {
        return Aluno::query()->create(array_merge([
            'nome' => 'Lara Lacerda',
            'email' => 'lara-'.uniqid().'@example.com',
            'recebe_email' => true,
            'ativo' => true,
        ], $extra));
    }

    /**
     * @param  array<string, mixed>  $avaliacao
     * @return array<string, mixed>
     */
    private function bloco(array $avaliacao, string $eixo): array
    {
        foreach ($avaliacao['blocos'] ?? [] as $bloco) {
            if (is_array($bloco) && ($bloco['eixo'] ?? '') === $eixo) {
                return $bloco;
            }
        }

        return [];
    }
}
