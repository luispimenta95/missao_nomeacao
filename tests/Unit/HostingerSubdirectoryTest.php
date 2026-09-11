<?php

namespace Tests\Unit;

use App\Http\HostingerSubdirectory;
use App\Providers\AppServiceProvider;
use PHPUnit\Framework\TestCase;

class HostingerSubdirectoryTest extends TestCase
{
    public function test_htaccess_da_raiz_envia_requisicoes_para_public(): void
    {
        $htaccess = (string) file_get_contents(dirname(__DIR__, 2).'/.htaccess');

        $this->assertStringContainsString('RewriteEngine On', $htaccess);
        $this->assertStringContainsString('RewriteRule ^public($|/) - [L]', $htaccess);
        $this->assertStringContainsString('RewriteRule ^(.*)$ public/$1 [L]', $htaccess);
        $this->assertStringContainsString('RewriteRule ^\\.env - [F,L]', $htaccess);
        $this->assertStringContainsString('vendor)/ - [F,L]', $htaccess);
    }

    public function test_front_controller_ajusta_script_name_antes_do_laravel(): void
    {
        $index = (string) file_get_contents(dirname(__DIR__, 2).'/public/index.php');

        $this->assertStringContainsString('HostingerSubdirectory::adjustServerVars', $index);
        $this->assertStringContainsString('Request::capture()', $index);
        $this->assertLessThan(
            strpos($index, 'Request::capture()'),
            strpos($index, 'adjustServerVars')
        );
    }

    public function test_remove_sufixo_public_do_app_url_da_hostinger(): void
    {
        $this->assertSame(
            'https://missaonomeacao.com.br/server',
            AppServiceProvider::urlSemSufixoPublic('https://missaonomeacao.com.br/server/public')
        );
        $this->assertSame(
            'https://missaonomeacao.com.br/server',
            AppServiceProvider::urlSemSufixoPublic('https://missaonomeacao.com.br/server/public/')
        );
        $this->assertSame('http://localhost', AppServiceProvider::urlSemSufixoPublic('http://localhost'));
        $this->assertSame('http://localhost', AppServiceProvider::urlSemSufixoPublic('http://localhost/'));
        $this->assertSame('', AppServiceProvider::urlSemSufixoPublic(null));
    }

    public function test_ajusta_script_name_quando_uri_nao_inclui_public(): void
    {
        $adjusted = HostingerSubdirectory::adjustServerVars([
            'REQUEST_URI' => '/server/login',
            'SCRIPT_NAME' => '/server/public/index.php',
            'PHP_SELF' => '/server/public/index.php',
        ]);

        $this->assertSame('/server/index.php', $adjusted['SCRIPT_NAME']);
        $this->assertSame('/server/index.php', $adjusted['PHP_SELF']);
        $this->assertSame('/server/login', $adjusted['REQUEST_URI']);
    }

    public function test_nao_altera_pedido_direto_em_public(): void
    {
        $server = [
            'REQUEST_URI' => '/server/public/login',
            'SCRIPT_NAME' => '/server/public/index.php',
            'PHP_SELF' => '/server/public/index.php',
        ];

        $this->assertSame($server, HostingerSubdirectory::adjustServerVars($server));
    }
}
