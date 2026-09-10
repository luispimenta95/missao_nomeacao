<?php

namespace Database\Factories;

use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Turma>
 */
class TurmaFactory extends Factory
{
    protected $model = Turma::class;

    public function definition(): array
    {
        $title = 'PRF Policial '.$this->faker->unique()->year();

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numerify('###'),
            'nome_publico' => 'PRF — Policial',
            'description' => 'Preparação direcionada para o concurso da PRF.',
            'checkout_url' => 'https://checkout.example.com/prf',
            'whatsapp_url' => 'https://wa.me/5561999999999',
            'interesse_url' => 'https://example.com/lista-prf',
            'start_date' => now()->addDays(15),
            'available_slots' => 30,
            'status' => 'aberta',
            'ativo' => true,
            'exibir_no_site' => true,
            'plano_pronto_tutory' => true,
            'aceitar_novos_alunos' => true,
            'grupo_exibicao' => Turma::GRUPO_TURMA_DIRECIONADA,
            'categoria_navegacao' => 'Policiais',
            'orgao' => 'PRF',
            'cargo' => 'Policial',
            'termos_busca' => 'PRF, Polícia Rodoviária Federal, Policial PRF',
            'momento_concurso' => 'previsto',
            'exibir_momento_concurso' => true,
            'acao_principal' => Turma::ACAO_CHECKOUT,
            'popup_opcoes' => [],
            'destacar_turmas_abertas' => true,
            'exibir_na_mentoria' => true,
            'ordem_exibicao' => 1,
            'secao_pagina' => 'Já tem um concurso ou carreira como alvo?',
            'texto_cta' => 'COMEÇAR AGORA',
        ];
    }

    public function emBreve(): static
    {
        return $this->state(fn () => [
            'aceitar_novos_alunos' => false,
            'acao_principal' => Turma::ACAO_WHATSAPP,
            'texto_cta' => null,
        ]);
    }

    public function listaInteresse(): static
    {
        return $this->state(fn () => [
            'aceitar_novos_alunos' => true,
            'acao_principal' => Turma::ACAO_LISTA,
            'texto_cta' => null,
        ]);
    }

    public function arquivada(): static
    {
        return $this->state(fn () => [
            'ativo' => false,
            'exibir_no_site' => false,
            'aceitar_novos_alunos' => false,
        ]);
    }

    public function ocultadaDoSite(): static
    {
        return $this->state(fn () => [
            'ativo' => true,
            'exibir_no_site' => false,
        ]);
    }
}
