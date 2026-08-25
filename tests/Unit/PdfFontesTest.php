<?php

namespace Tests\Unit;

use App\Models\Configuracao;
use App\Services\Tutory\PdfFontes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfFontesTest extends TestCase
{
    use RefreshDatabase;

    public function test_padrao_e_inter_quando_as_fontes_existem(): void
    {
        $this->assertNotNull(PdfFontes::diretorioInter());
        $this->assertSame('inter', PdfFontes::chaveAtual());
        $this->assertSame('Inter', PdfFontes::familiaDompdf());
        $this->assertSame('DejaVu Sans', PdfFontes::defaultFontDompdf());
        $this->assertSame('DejaVu Sans', PdfFontes::opcoesDompdf()->getDefaultFont());
        $this->assertStringContainsString('@font-face', PdfFontes::fontFaceCss());
        $this->assertStringContainsString("url('resources/fonts/inter/Inter-Regular.ttf')", PdfFontes::fontFaceCss());
        $this->assertStringNotContainsString('file://', PdfFontes::fontFaceCss());
    }

    public function test_admin_prevalece_sobre_env(): void
    {
        Configuracao::definir(PdfFontes::CONFIG_CHAVE, 'times');

        $this->assertSame('times', PdfFontes::chaveAtual());
        $this->assertSame('Times-Roman', PdfFontes::familiaDompdf());
    }

    public function test_chave_invalida_volta_ao_padrao(): void
    {
        $this->assertSame('inter', PdfFontes::normalizar('comic-sans'));
        $this->assertFalse(PdfFontes::ehValida('fredoka'));
    }
}
