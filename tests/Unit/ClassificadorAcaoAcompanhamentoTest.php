<?php

namespace Tests\Unit;

use App\Enums\AcaoAcompanhamento;
use App\Enums\TipoMotivoAcompanhamento;
use App\Services\Acompanhamento\ClassificadorAcaoAcompanhamento;
use App\Services\Acompanhamento\ContextoAcompanhamento;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClassificadorAcaoAcompanhamentoTest extends TestCase
{
    private ClassificadorAcaoAcompanhamento $classificador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classificador = new ClassificadorAcaoAcompanhamento;
    }

    public function test_parabeniza_quando_um_parametro_evolui_e_nenhum_piora(): void
    {
        $ficha = $this->classificador->classificar($this->contexto([
            'constanciaAtual' => 'bom',
            'constanciaAnterior' => 'brigando',
            'constanciaAtualNome' => 'Bom',
            'constanciaAnteriorNome' => 'Brigando com a constância',
            'volumeAtual' => 'volume_suficiente',
            'volumeAnterior' => 'volume_suficiente',
            'desempenhoAtual' => 'mediano',
            'desempenhoAnterior' => 'mediano',
        ]));

        $this->assertNotNull($ficha);
        $this->assertSame(AcaoAcompanhamento::Parabenizar, $ficha->acao);
        $this->assertSame(
            'Constância evoluiu: Brigando com a constância → Bom',
            $ficha->motivoPrincipal()?->texto
        );
    }

    public function test_nao_parabeniza_quando_outro_parametro_piora(): void
    {
        $ficha = $this->classificador->classificar($this->contexto([
            'constanciaAtual' => 'bom',
            'constanciaAnterior' => 'brigando',
            'constanciaAtualNome' => 'Bom',
            'constanciaAnteriorNome' => 'Brigando com a constância',
            'volumeAtual' => 'volume_baixo',
            'volumeAnterior' => 'volume_suficiente',
            'volumeAtualNome' => 'Volume baixo',
            'volumeAnteriorNome' => 'Volume suficiente',
            'desempenhoAtual' => 'mediano',
            'desempenhoAnterior' => 'mediano',
        ]));

        $this->assertNull($ficha);
    }

    public function test_constancia_critica_gera_intervir(): void
    {
        $ficha = $this->classificador->classificar($this->contexto([
            'constanciaAtual' => 'critico',
            'constanciaAtualNome' => 'Crítico',
        ]));

        $this->assertSame(AcaoAcompanhamento::Intervir, $ficha?->acao);
        $this->assertSame('Constância crítica', $ficha?->motivoPrincipal()?->texto);
    }

    public function test_volume_critico_e_inconclusivo_gera_intervir(): void
    {
        $ficha = $this->classificador->classificar($this->contexto([
            'volumeAtual' => 'critico_inconclusivo',
            'volumeAtualNome' => 'Crítico e inconclusivo',
        ]));

        $this->assertSame(AcaoAcompanhamento::Intervir, $ficha?->acao);
        $this->assertSame('Volume de questões crítico', $ficha?->motivoPrincipal()?->texto);
    }

    public function test_volume_baixo_nao_gera_intervir(): void
    {
        $ficha = $this->classificador->classificar($this->contexto([
            'volumeAtual' => 'volume_baixo',
            'volumeAtualNome' => 'Volume baixo',
        ]));

        $this->assertNull($ficha);
    }

    public function test_dois_parametros_criticos_entram_so_em_intervir_com_os_dois_motivos(): void
    {
        $ficha = $this->classificador->classificar($this->contexto([
            'constanciaAtual' => 'critico',
            'volumeAtual' => 'critico_inconclusivo',
        ]));

        $this->assertSame(AcaoAcompanhamento::Intervir, $ficha?->acao);
        $this->assertSame(
            ['Constância crítica', 'Volume de questões crítico'],
            array_map(static fn ($motivo) => $motivo->texto, $ficha?->motivos ?? [])
        );
    }

    public function test_desempenho_baixo_marca_presenca(): void
    {
        $ficha = $this->classificador->classificar($this->contexto([
            'desempenhoAtual' => 'critico',
            'desempenhoAtualNome' => 'Crítico',
        ]));

        $this->assertSame(AcaoAcompanhamento::MarcarPresenca, $ficha?->acao);
        $this->assertSame('Desempenho em questões: baixo', $ficha?->motivoPrincipal()?->texto);
    }

    public function test_alerta_de_desempenho_tambem_e_baixo(): void
    {
        $ficha = $this->classificador->classificar($this->contexto([
            'desempenhoAtual' => 'alerta',
        ]));

        $this->assertSame(AcaoAcompanhamento::MarcarPresenca, $ficha?->acao);
        $this->assertTrue($ficha?->tem(TipoMotivoAcompanhamento::DesempenhoBaixo));
    }

    public function test_assunto_baixo_marca_presenca_e_abre_ponte_com_protocolo(): void
    {
        $ficha = $this->classificador->classificar($this->contexto([
            'assuntos' => [[
                'disciplina' => 'Administrativo',
                'assunto' => 'Atos Administrativos',
                'faixa' => 'abaixo_media',
                'faixa_nome' => 'Abaixo da média',
                'percentual' => 70,
            ]],
        ]));

        $this->assertSame(AcaoAcompanhamento::MarcarPresenca, $ficha?->acao);
        $this->assertSame(
            'Administrativo · Atos Administrativos: desempenho baixo',
            $ficha?->motivoPrincipal()?->texto
        );
        $this->assertTrue($ficha?->motivoPrincipal()?->ponteProtocoloResgate);
    }

    public function test_hierarquia_mostra_intervir_com_motivo_de_presenca_na_ficha(): void
    {
        $ficha = $this->classificador->classificar($this->contexto([
            'constanciaAtual' => 'critico',
            'desempenhoAtual' => 'critico',
        ]));

        $this->assertSame(AcaoAcompanhamento::Intervir, $ficha?->acao);
        $textos = array_map(static fn ($motivo) => $motivo->texto, $ficha?->motivos ?? []);
        $this->assertSame(['Constância crítica', 'Desempenho em questões: baixo'], $textos);
        $this->assertFalse($ficha?->tem(TipoMotivoAcompanhamento::Evolucao) ?? true);
    }

    public function test_intervir_vence_presenca_e_parabenizar_e_a_ficha_guarda_os_tres(): void
    {
        $ficha = $this->classificador->classificar($this->contexto([
            'constanciaAtual' => 'bom',
            'constanciaAnterior' => 'brigando',
            'constanciaAtualNome' => 'Bom',
            'constanciaAnteriorNome' => 'Brigando com a constância',
            'volumeAtual' => 'critico_inconclusivo',
            'volumeAnterior' => 'critico_inconclusivo',
            'volumeAtualNome' => 'Crítico e inconclusivo',
            'volumeAnteriorNome' => 'Crítico e inconclusivo',
            'desempenhoAtual' => 'critico',
            'desempenhoAnterior' => 'critico',
            'desempenhoAtualNome' => 'Crítico',
            'desempenhoAnteriorNome' => 'Crítico',
            'diasSemContato' => 18,
        ]));

        $this->assertSame(AcaoAcompanhamento::Intervir, $ficha?->acao);
        $this->assertTrue($ficha?->tem(TipoMotivoAcompanhamento::VolumeCritico));
        $this->assertTrue($ficha?->tem(TipoMotivoAcompanhamento::DesempenhoBaixo));
        $this->assertTrue($ficha?->tem(TipoMotivoAcompanhamento::SemContato));
        $this->assertTrue($ficha?->tem(TipoMotivoAcompanhamento::Evolucao));
        $this->assertSame(TipoMotivoAcompanhamento::VolumeCritico, $ficha?->motivos[0]->tipo);
    }

    public function test_tempo_sem_contato_marca_presenca(): void
    {
        $ficha = $this->classificador->classificar($this->contexto([
            'diasSemContato' => 16,
        ]));

        $this->assertSame(AcaoAcompanhamento::MarcarPresenca, $ficha?->acao);
        $this->assertSame('16 dias sem contato', $ficha?->motivoPrincipal()?->texto);
    }

    public function test_quinze_dias_sem_contato_ainda_nao_disparam(): void
    {
        $this->assertNull($this->classificador->classificar($this->contexto([
            'diasSemContato' => 15,
        ])));
    }

    public function test_contato_programado_para_hoje_marca_presenca(): void
    {
        $ficha = $this->classificador->classificar($this->contexto([
            'contatoProgramadoHoje' => true,
        ]));

        $this->assertSame('Contato programado para hoje', $ficha?->motivoPrincipal()?->texto);
    }

    public function test_sem_sinal_nao_gera_acao(): void
    {
        $this->assertNull($this->classificador->classificar($this->contexto()));
    }

    #[DataProvider('faixasQueNaoIntervem')]
    public function test_faixa_estavel_nao_gera_acao(string $campo, string $codigo): void
    {
        $this->assertNull($this->classificador->classificar($this->contexto([
            $campo => $codigo,
        ])));
    }

    public static function faixasQueNaoIntervem(): array
    {
        return [
            'constância boa' => ['constanciaAtual', 'bom'],
            'constância brigando' => ['constanciaAtual', 'brigando'],
            'volume suficiente' => ['volumeAtual', 'volume_suficiente'],
            'desempenho mediano' => ['desempenhoAtual', 'mediano'],
            'desempenho muito bom' => ['desempenhoAtual', 'muito_bom'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    private function contexto(array $override = []): ContextoAcompanhamento
    {
        $dados = array_merge([
            'constanciaAtual' => null,
            'constanciaAnterior' => null,
            'constanciaAtualNome' => null,
            'constanciaAnteriorNome' => null,
            'volumeAtual' => null,
            'volumeAnterior' => null,
            'volumeAtualNome' => null,
            'volumeAnteriorNome' => null,
            'desempenhoAtual' => null,
            'desempenhoAnterior' => null,
            'desempenhoAtualNome' => null,
            'desempenhoAnteriorNome' => null,
            'assuntos' => [],
            'diasSemContato' => 0,
            'contatoProgramadoHoje' => false,
        ], $override);

        return new ContextoAcompanhamento(...$dados);
    }
}
