<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\EixoDesempenho;
use App\Models\FaixaDesempenho;
use App\Models\User;
use App\Models\UsoTextoDesempenho;
use Database\Seeders\ParametrosDesempenhoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesempenhoTextosAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ParametrosDesempenhoSeeder::class);
    }

    public function test_mentor_cadastra_outro_texto_sem_apagar_o_atual(): void
    {
        $faixa = $this->faixaConstanciaCritica();
        $canonico = $faixa->textos()->where('canonico', true)->firstOrFail();
        $textoAtual = $canonico->texto;
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('desempenho.edit', $faixa))
            ->assertOk()
            ->assertSee('Adicionar texto')
            ->assertSee('Outros textos desta faixa')
            ->assertSee($textoAtual, false);

        $this->actingAs($user)
            ->put(route('desempenho.update', $faixa), $this->payload($faixa, [
                'alternativos' => [
                    ['texto' => 'Texto novo para quem permanecer em crítico.'],
                ],
            ]))
            ->assertRedirect(route('desempenho.index'));

        $faixa->refresh();
        $this->assertSame($textoAtual, $faixa->texto_email);
        $this->assertSame($textoAtual, $canonico->fresh()->texto);
        $this->assertTrue($canonico->fresh()->canonico);
        $this->assertDatabaseHas('textos_faixa_desempenho', [
            'faixa_desempenho_id' => $faixa->id,
            'texto' => 'Texto novo para quem permanecer em crítico.',
            'canonico' => false,
            'ativo' => true,
            'ordem' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('desempenho.index'))
            ->assertOk()
            ->assertSee('Editar textos');
    }

    public function test_remover_texto_usado_desativa_e_texto_sem_uso_sai(): void
    {
        $faixa = $this->faixaConstanciaCritica();
        $usado = $faixa->textos()->create([
            'texto' => 'Texto já enviado.',
            'ordem' => 2,
            'canonico' => false,
            'ativo' => true,
        ]);
        $semUso = $faixa->textos()->create([
            'texto' => 'Texto ainda não enviado.',
            'ordem' => 3,
            'canonico' => false,
            'ativo' => true,
        ]);
        $aluno = Aluno::query()->create([
            'nome' => 'Lara Lacerda',
            'email' => 'lara@example.com',
            'recebe_email' => true,
        ]);
        UsoTextoDesempenho::query()->create([
            'aluno_id' => $aluno->id,
            'faixa_desempenho_id' => $faixa->id,
            'texto_faixa_desempenho_id' => $usado->id,
            'eixo_codigo' => EixoDesempenho::CONSTANCIA,
            'ciclo' => 1,
            'periodo' => '2026-09-2',
        ]);

        $this->actingAs(User::factory()->create())
            ->put(route('desempenho.update', $faixa), $this->payload($faixa))
            ->assertRedirect(route('desempenho.index'));

        $this->assertFalse($usado->fresh()->ativo);
        $this->assertNull($semUso->fresh());
        $this->assertNotNull($faixa->textos()->where('canonico', true)->first());
    }

    private function faixaConstanciaCritica(): FaixaDesempenho
    {
        return FaixaDesempenho::query()
            ->where('codigo', 'critico')
            ->whereHas('eixo', static fn ($q) => $q->where('codigo', EixoDesempenho::CONSTANCIA))
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(FaixaDesempenho $faixa, array $extra = []): array
    {
        return array_merge([
            'nome' => $faixa->nome,
            'valor_min' => $faixa->valor_min,
            'valor_max' => $faixa->valor_max,
            'ordem' => $faixa->ordem,
            'texto_email' => $faixa->texto_email,
            'ativo' => '1',
        ], $extra);
    }
}
