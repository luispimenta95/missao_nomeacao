<?php

namespace Tests\Feature;

use App\Models\Configuracao;
use App\Models\User;
use App\Services\Alunos\TextoBoasVindasNovato;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoasVindasAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_grava_um_unico_texto_de_boas_vindas(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('boas-vindas.edit'))
            ->assertOk()
            ->assertSee('Não há variações nem rodízio')
            ->assertSee('Olá, {NOME}!')
            ->assertDontSee('Adicionar texto');

        $this->actingAs($user)
            ->put(route('boas-vindas.update'), [
                'texto' => 'Oi, {NOME}. Seu lugar na mentoria está pronto.',
            ])
            ->assertRedirect(route('boas-vindas.edit'))
            ->assertSessionHas('success');

        $this->assertSame(1, Configuracao::query()->where('chave', TextoBoasVindasNovato::CHAVE)->count());
        $this->assertSame(
            'Oi, Ana. Seu lugar na mentoria está pronto.',
            TextoBoasVindasNovato::texto('Ana')
        );

        $this->actingAs($user)
            ->from(route('boas-vindas.edit'))
            ->put(route('boas-vindas.update'), ['texto' => '   '])
            ->assertSessionHasErrors('texto');
    }
}
