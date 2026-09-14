<?php

namespace Tests\Unit;

use App\Console\Commands\BaixarRelatoriosTutoryCommand;
use App\Services\Tutory\CoachReportDownloader;
use ReflectionClass;
use Tests\TestCase;

class AlunaTesteRelatorioTest extends TestCase
{
    public function test_aluna_teste_e_giovanna(): void
    {
        $ref = new ReflectionClass(CoachReportDownloader::class);

        $this->assertSame('Giovanna', $ref->getConstant('ALUNA_TESTE'));
    }

    public function test_comando_describe_giovanna_no_modo_teste(): void
    {
        $comando = new BaixarRelatoriosTutoryCommand;
        $descricao = $comando->getDefinition()->getOption('teste')->getDescription();

        $this->assertStringContainsString('Giovanna', $descricao);
        $this->assertStringNotContainsString('Laíra', $descricao);
    }
}
