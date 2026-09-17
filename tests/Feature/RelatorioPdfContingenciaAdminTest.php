<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\User;
use App\Services\Tutory\RelatorioPdfContingencia;
use App\Services\Tutory\RelatorioPeriodoCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class RelatorioPdfContingenciaAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_visitante_nao_acessa_a_contingencia(): void
    {
        $this->get(route('relatorios-pdf-contingencia.index'))->assertRedirect(route('login'));
        $this->post(route('relatorios-pdf-contingencia.gerar'), [])->assertRedirect(route('login'));
    }

    public function test_admin_ve_alunos_ativos_e_os_12_periodos_da_janela(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 12:00:00', 'America/Sao_Paulo'));
        $user = User::factory()->create();
        Aluno::create([
            'tutory_id' => '7711',
            'nome' => 'Giovanna',
            'email' => 'giovanna@example.com',
            'recebe_email' => true,
        ]);
        Aluno::create([
            'nome' => 'Sem Tutory',
            'email' => 'local@example.com',
            'recebe_email' => false,
        ]);

        $html = $this->actingAs($user)
            ->get(route('relatorios-pdf-contingencia.index'))
            ->assertOk()
            ->assertSee('PDF de meses anteriores')
            ->assertSee('Giovanna')
            ->assertDontSee('Sem Tutory')
            ->assertSee('MARÇO - PERÍODO 2')
            ->assertSee('SETEMBRO - PERÍODO 1')
            ->assertDontSee('JANEIRO - PERÍODO 1')
            ->assertDontSee('SETEMBRO - PERÍODO 2')
            ->assertDontSee('OUTUBRO - PERÍODO 1')
            ->assertSee('Gerando PDF')
            ->assertSee('Meses visíveis')
            ->getContent();

        $this->assertSame(12, substr_count($html, 'value="2026-'));
    }

    public function test_em_primeiro_de_outubro_o_periodo_2_de_setembro_aparece(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00', 'America/Sao_Paulo'));
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('relatorios-pdf-contingencia.index'))
            ->assertOk()
            ->assertSee('SETEMBRO - PERÍODO 2')
            ->assertSee('2026-09|2')
            ->assertDontSee('OUTUBRO - PERÍODO 1')
            ->assertDontSee('2026-10|1');
    }

    public function test_admin_altera_a_janela_de_meses(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 12:00:00', 'America/Sao_Paulo'));
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('relatorios-pdf-contingencia.update'), ['meses' => 3])
            ->assertRedirect(route('relatorios-pdf-contingencia.index'));

        $this->assertSame('3', Configuracao::valor(RelatorioPeriodoCatalog::CONFIG_CHAVE));

        $html = $this->actingAs($user)
            ->get(route('relatorios-pdf-contingencia.index'))
            ->assertOk()
            ->assertSee('SETEMBRO - PERÍODO 1')
            ->assertDontSee('MAIO - PERÍODO 1')
            ->getContent();

        $this->assertSame(6, substr_count($html, 'value="2026-'));
    }

    public function test_rejeita_periodo_ainda_nao_liberado_e_mais_de_um_aluno(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 12:00:00', 'America/Sao_Paulo'));
        $user = User::factory()->create();
        $aluno = Aluno::create([
            'tutory_id' => '7711',
            'nome' => 'Giovanna',
            'email' => 'giovanna@example.com',
            'recebe_email' => true,
        ]);

        $this->actingAs($user)
            ->from(route('relatorios-pdf-contingencia.index'))
            ->post(route('relatorios-pdf-contingencia.gerar'), [
                'aluno_id' => $aluno->id,
                'periodo' => '2026-09|2',
            ])
            ->assertRedirect(route('relatorios-pdf-contingencia.index'))
            ->assertSessionHasErrors('periodo');

        $this->actingAs($user)
            ->from(route('relatorios-pdf-contingencia.index'))
            ->post(route('relatorios-pdf-contingencia.gerar'), [
                'aluno_id' => [$aluno->id],
                'periodo' => '2026-09|1',
            ])
            ->assertRedirect(route('relatorios-pdf-contingencia.index'))
            ->assertSessionHasErrors('aluno_id');
    }

    public function test_gera_pdf_para_download_sem_enviar_email(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 12:00:00', 'America/Sao_Paulo'));
        Mail::fake();
        $user = User::factory()->create();
        $aluno = Aluno::create([
            'tutory_id' => '7711',
            'nome' => 'Giovanna',
            'email' => 'giovanna@example.com',
            'recebe_email' => true,
            'last_performance' => 'Excelente',
        ]);

        $caminho = sys_get_temp_dir().'/relatorio_consolidado_contingencia_'.uniqid('', true).'.pdf';
        file_put_contents($caminho, '%PDF-1.4 contingencia');

        $fake = Mockery::mock(RelatorioPdfContingencia::class);
        $fake->shouldReceive('gerar')
            ->once()
            ->withArgs(function (Aluno $recebido, array $periodo) use ($aluno): bool {
                return $recebido->is($aluno)
                    && $periodo['year_month'] === '2026-09'
                    && $periodo['period'] === '1';
            })
            ->andReturn($caminho);
        $this->app->instance(RelatorioPdfContingencia::class, $fake);

        $response = $this->actingAs($user)
            ->post(route('relatorios-pdf-contingencia.gerar'), [
                'aluno_id' => $aluno->id,
                'periodo' => '2026-09|1',
            ]);

        $response->assertOk();
        $response->assertDownload(basename($caminho));
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        Mail::assertNothingSent();
        $this->assertSame('Excelente', $aluno->fresh()->last_performance);
    }
}
