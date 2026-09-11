<?php

namespace Tests\Unit;

use App\Models\Turma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TurmaEstagioTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function publica_sem_aceitar_alunos_fica_em_breve_com_cta_de_whatsapp(): void
    {
        $turma = Turma::factory()->emBreve()->create([
            'whatsapp_url' => 'https://wa.me/5561999999999',
        ]);

        $this->assertSame(Turma::ESTAGIO_EM_BREVE, $turma->estagio());
        $this->assertSame('EM BREVE', $turma->badgePublico());
        $this->assertSame('ENTRAR NO GRUPO', $turma->textoCtaPublico());
        $this->assertSame('https://wa.me/5561999999999', $turma->linkCta());
        $this->assertFalse($turma->aceitaInscricao());
        $this->assertSame('fechada', $turma->status);
    }

    #[Test]
    public function acao_lista_gera_lista_de_interesse(): void
    {
        $turma = Turma::factory()->listaInteresse()->create([
            'interesse_url' => 'https://example.com/lista',
        ]);

        $this->assertSame(Turma::ESTAGIO_LISTA_INTERESSE, $turma->estagio());
        $this->assertSame('LISTA DE INTERESSE', $turma->badgePublico());
        $this->assertSame('QUERO SER AVISADO', $turma->textoCtaPublico());
        $this->assertSame('https://example.com/lista', $turma->linkCta());
    }

    #[Test]
    public function checkout_com_alunos_liberados_abre_inscricoes(): void
    {
        $turma = Turma::factory()->create([
            'texto_cta' => null,
            'checkout_url' => 'https://example.com/pay',
        ]);

        $this->assertSame(Turma::ESTAGIO_INSCRICOES_ABERTAS, $turma->estagio());
        $this->assertSame('INSCRIÇÕES ABERTAS', $turma->badgePublico());
        $this->assertSame('COMEÇAR AGORA', $turma->textoCtaPublico());
        $this->assertSame('https://example.com/pay', $turma->linkCta());
        $this->assertTrue($turma->aceitaInscricao());
    }

    #[Test]
    public function momento_do_concurso_so_aparece_quando_admin_marca_para_exibir(): void
    {
        $oculta = Turma::factory()->create([
            'momento_concurso' => 'previsto',
            'exibir_momento_concurso' => false,
        ]);
        $visivel = Turma::factory()->create([
            'momento_concurso' => 'edital_iminente',
            'exibir_momento_concurso' => true,
        ]);

        $this->assertNull($oculta->momentoConcursoPublico());
        $this->assertSame('EDITAL IMINENTE', $visivel->momentoConcursoPublico());
    }
}
