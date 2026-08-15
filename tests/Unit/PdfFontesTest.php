<?php

namespace Tests\Unit;

use App\Models\Configuracao;
use App\Services\Tutory\PdfFontes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfFontesTest extends TestCase
{
    use RefreshDatabase;

    public function test_padrao_e_dejavu_quando_nada_foi_salvo(): void
    {
        $this->assertSame('dejavu', PdfFontes::chaveAtual());
        $this->assertSame('DejaVu Sans', PdfFontes::familiaDompdf());
    }

    public function test_admin_prevalece_sobre_env(): void
    {
        Configuracao::definir(PdfFontes::CONFIG_CHAVE, 'times');

        $this->assertSame('times', PdfFontes::chaveAtual());
        $this->assertSame('Times-Roman', PdfFontes::familiaDompdf());
    }

    public function test_chave_invalida_volta_ao_padrao(): void
    {
        $this->assertSame('dejavu', PdfFontes::normalizar('comic-sans'));
        $this->assertFalse(PdfFontes::ehValida('fredoka'));
    }
}
