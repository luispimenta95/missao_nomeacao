<?php

namespace App\Http\Requests;

use App\Models\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TurmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $opcoes = $this->input('popup_opcoes', []);

        if (is_array($opcoes)) {
            $opcoes = collect($opcoes)
                ->map(function ($opcao) {
                    return [
                        'label' => trim((string) ($opcao['label'] ?? '')),
                        'url' => trim((string) ($opcao['url'] ?? '')),
                    ];
                })
                ->filter(fn ($opcao) => $opcao['label'] !== '' || $opcao['url'] !== '')
                ->values()
                ->all();
        }

        $this->merge([
            'popup_opcoes' => $opcoes,
            'slug' => filled($this->input('slug')) ? $this->input('slug') : null,
        ]);
    }

    public function rules(): array
    {
        $turma = $this->route('turma');

        return [
            'title' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('turmas', 'slug')->ignore($turma),
            ],
            'nome_publico' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|file|mimes:png,jpg,jpeg,svg,webp|max:5120',
            'checkout_url' => 'nullable|url|max:500',
            'whatsapp_url' => 'nullable|url|max:500',
            'interesse_url' => 'nullable|url|max:500',
            'start_date' => 'nullable|date',
            'available_slots' => 'nullable|integer|min:0',
            'grupo_exibicao' => ['required', Rule::in(array_keys(Turma::GRUPOS_EXIBICAO))],
            'categoria_navegacao' => 'nullable|string|max:255',
            'orgao' => 'nullable|string|max:255',
            'cargo' => 'nullable|string|max:255',
            'termos_busca' => 'nullable|string|max:2000',
            'momento_concurso' => ['nullable', Rule::in(array_keys(Turma::MOMENTOS_CONCURSO))],
            'acao_principal' => ['required', Rule::in(array_keys(Turma::ACOES_PRINCIPAIS))],
            'popup_opcoes' => 'nullable|array',
            'popup_opcoes.*.label' => 'required_with:popup_opcoes.*.url|string|max:255',
            'popup_opcoes.*.url' => 'required_with:popup_opcoes.*.label|url|max:500',
            'ordem_exibicao' => 'nullable|integer|min:0|max:9999',
            'secao_pagina' => 'nullable|string|max:255',
            'texto_cta' => 'nullable|string|max:80',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Informe o nome interno da preparação.',
            'popup_opcoes.*.label.required_with' => 'Cada opção do popup precisa de um nome.',
            'popup_opcoes.*.url.required_with' => 'Cada opção do popup precisa de um destino.',
            'popup_opcoes.*.url.url' => 'O destino da opção do popup deve ser uma URL válida.',
        ];
    }

    public function payload(): array
    {
        $data = $this->validated();

        $data['ativo'] = $this->boolean('ativo');
        $data['exibir_no_site'] = $this->boolean('exibir_no_site');
        $data['plano_pronto_tutory'] = $this->boolean('plano_pronto_tutory');
        $data['aceitar_novos_alunos'] = $this->boolean('aceitar_novos_alunos');
        $data['exibir_momento_concurso'] = $this->boolean('exibir_momento_concurso');
        $data['destacar_turmas_abertas'] = $this->boolean('destacar_turmas_abertas');
        $data['exibir_na_mentoria'] = $this->boolean('exibir_na_mentoria');
        $data['ordem_exibicao'] = (int) ($data['ordem_exibicao'] ?? 0);
        $data['popup_opcoes'] = array_values($data['popup_opcoes'] ?? []);
        $data['nome_publico'] = filled($data['nome_publico'] ?? null) ? $data['nome_publico'] : $data['title'];
        $data['checkout_url'] = $data['checkout_url'] ?? '';
        $data['whatsapp_url'] = $data['whatsapp_url'] ?? null;
        $data['interesse_url'] = $data['interesse_url'] ?? null;

        unset($data['logo']);

        return $data;
    }
}
