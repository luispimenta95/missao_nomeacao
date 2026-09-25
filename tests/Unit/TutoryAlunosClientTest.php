<?php

namespace Tests\Unit;

use App\Services\Tutory\TutoryAlunosClient;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use Tests\TestCase;

class TutoryAlunosClientTest extends TestCase
{
    public function test_extrai_nome_id_e_email_dos_cards(): void
    {
        $html = <<<'HTML'
<html><body>
<div class="pesquisa-aluno-container">
  <span class="pesquisa-aluno-nome">Giovanna</span>
  <a class="btn-generate-report" data-id="7711" href="#">Gerar</a>
  giovanna@example.com
</div>
<div class="pesquisa-aluno-container" data-email="bruno@example.com">
  <span class="pesquisa-aluno-nome">Bruno Costa</span>
  <a class="btn-generate-report" data-id="7712" href="#">Gerar</a>
</div>
</body></html>
HTML;

        $client = new TutoryAlunosClient(static function (): void {});
        $ref = new ReflectionClass($client);
        $metodo = $ref->getMethod('parseAlunosDaPagina');
        $alunos = $metodo->invoke($client, $html);

        $this->assertSame([
            ['id' => '7711', 'nome' => 'Giovanna', 'email' => 'giovanna@example.com'],
            ['id' => '7712', 'nome' => 'Bruno Costa', 'email' => 'bruno@example.com'],
        ], $alunos);
    }

    public function test_login_e_coleta_alunos_ativos(): void
    {
        Http::fake([
            'admin.tutory.com.br/login' => Http::response('<html>ok</html>', 200),
            'admin.tutory.com.br/intent/login' => Http::response(['result' => true], 200),
            'admin.tutory.com.br/index' => Http::response("adminUser = { token: 'abc123' };", 200),
            'admin.tutory.com.br/alunos/consulta*' => Http::response(
                '<html><body><div class="pesquisa-aluno-container">'
                .'<span class="pesquisa-aluno-nome">Lara</span>'
                .'<a class="btn-generate-report" data-id="88" href="#"></a>'
                .'lara@example.com'
                .'</div></body></html>',
                200
            ),
        ]);

        $client = new TutoryAlunosClient(static function (): void {});
        $client->login();
        $alunos = $client->coletarAlunosAtivos();
        $client->encerrar();

        $this->assertSame([
            ['id' => '88', 'nome' => 'Lara', 'email' => 'lara@example.com'],
        ], $alunos);
    }
}
