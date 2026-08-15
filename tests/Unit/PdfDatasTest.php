<?php

namespace Tests\Unit;

use App\Services\Tutory\PdfDatas;
use Tests\TestCase;

class PdfDatasTest extends TestCase
{
    public function test_converte_periodo_americano_para_brasileiro(): void
    {
        $this->assertSame(
            'Período do relatório: de 01/08/2026 a 15/08/2026',
            PdfDatas::textoParaBr('Período do relatório: de 08/01/2026 a 08/15/2026')
        );
    }

    public function test_mantem_periodo_ja_brasileiro(): void
    {
        $this->assertSame(
            '01/08/2026 a 15/08/2026',
            PdfDatas::textoParaBr('01/08/2026 a 15/08/2026')
        );
        $this->assertSame(
            '16/08/2026 a 31/08/2026',
            PdfDatas::textoParaBr('16/08/2026 a 31/08/2026')
        );
    }

    public function test_converte_iso_para_brasileiro(): void
    {
        $this->assertSame(
            'de 01/08/2026 a 15/08/2026',
            PdfDatas::textoParaBr('de 2026-08-01 a 2026-08-15')
        );
    }

    public function test_converte_segunda_quinzena_americana(): void
    {
        $this->assertSame(
            '16/08/2026 a 31/08/2026',
            PdfDatas::textoParaBr('08/16/2026 a 08/31/2026')
        );
    }

    public function test_converte_eixo_de_grafico_americano(): void
    {
        $this->assertSame(
            ['01/08', '02/08', '15/08'],
            PdfDatas::listaParaBr(['08/01', '08/02', '08/15'])
        );
    }

    public function test_mantem_eixo_ja_brasileiro(): void
    {
        $this->assertSame(
            ['01/08', '02/08', '15/08'],
            PdfDatas::listaParaBr(['01/08', '02/08', '15/08'])
        );
    }

    public function test_converte_mes_por_extenso_ingles(): void
    {
        $this->assertSame('01/08/2026', PdfDatas::textoParaBr('August 1, 2026'));
        $this->assertSame('15/08', PdfDatas::textoParaBr('Aug 15'));
    }

    public function test_nao_inverte_data_ambigua_sozinha(): void
    {
        $this->assertSame('01/08/2026', PdfDatas::textoParaBr('01/08/2026'));
    }
}
