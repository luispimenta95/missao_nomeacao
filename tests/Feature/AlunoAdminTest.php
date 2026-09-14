<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlunoAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_nao_cria_aluno_com_nome_duplicado(): void
    {
        $user = User::factory()->create();
        Aluno::create([
            'nome' => 'Giovanna',
            'email' => 'giovanna@example.com',
            'recebe_email' => true,
        ]);

        $this->actingAs($user)
            ->from(route('alunos.create'))
            ->post(route('alunos.store'), [
                'nome' => 'Giovanna',
                'email' => 'outra@example.com',
                'recebe_email' => '1',
            ])
            ->assertRedirect(route('alunos.create'))
            ->assertSessionHasErrors('nome');

        $this->assertSame(1, Aluno::query()->count());
    }

    public function test_comando_de_sincronizacao_esta_agendado_as_6h(): void
    {
        $src = (string) file_get_contents(base_path('routes/console.php'));
        $this->assertStringContainsString("Schedule::command('tutory:sincronizar-alunos')", $src);
        $this->assertStringContainsString("->monthlyOn(1, '06:00')", $src);
        $this->assertStringContainsString("->monthlyOn(16, '06:00')", $src);
        $this->assertStringContainsString("->monthlyOn(01, '10:30')", $src);
        $this->assertStringContainsString("->monthlyOn(16, '10:30')", $src);
    }
}
