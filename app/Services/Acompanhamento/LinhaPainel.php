<?php

namespace App\Services\Acompanhamento;

use App\Enums\SituacaoAcompanhamento;
use App\Models\Aluno;

final class LinhaPainel
{
    /**
     * @param  list<array{disciplina: string, assunto: string, faixa: string, faixa_nome: string, percentual: float|null}>  $assuntos
     */
    public function __construct(
        public Aluno $aluno,
        public FichaAcompanhamento $ficha,
        public SituacaoAcompanhamento $situacao,
        public string $iniciais,
        public string $ultimoContato,
        public string $proximoContato,
        public bool $proximoEhHoje,
        public bool $somenteAgenda,
        public array $assuntos,
    ) {}
}
