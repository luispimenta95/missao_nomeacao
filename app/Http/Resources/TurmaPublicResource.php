<?php

namespace App\Http\Resources;

use App\Models\Turma;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Turma */
class TurmaPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'nome_publico' => $this->nomePublicoExibido(),
            'descricao_curta' => $this->description,
            'secao_pagina' => $this->secaoPaginaPublica(),
            'grupo_exibicao' => $this->grupo_exibicao,
            'grupo_exibicao_label' => Turma::GRUPOS_EXIBICAO[$this->grupo_exibicao] ?? $this->grupo_exibicao,
            'categoria' => $this->categoria_navegacao,
            'orgao' => $this->orgao,
            'cargo' => $this->cargo,
            'termos_busca' => $this->termos_busca,
            'momento_concurso' => $this->momentoConcursoPublico(),
            'momento_concurso_chave' => $this->exibir_momento_concurso ? $this->momento_concurso : null,
            'estagio' => $this->estagio(),
            'estagio_label' => Turma::ESTAGIOS[$this->estagio()] ?? $this->estagio(),
            'badge' => $this->badgePublico(),
            'texto_cta' => $this->textoCtaPublico(),
            'link_cta' => $this->linkCta(),
            'acao_principal' => $this->acao_principal,
            'capa_url' => $this->capaUrl(),
            'popup_opcoes' => $this->popupOpcoesNormalizadas(),
            'destacar_turmas_abertas' => (bool) $this->destacar_turmas_abertas,
            'exibir_na_mentoria' => (bool) $this->exibir_na_mentoria,
            'ordem_exibicao' => (int) $this->ordem_exibicao,
            'aceitar_novos_alunos' => (bool) $this->aceitar_novos_alunos,
        ];
    }
}
