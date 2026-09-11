<?php

namespace Tests\Unit;

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
}
