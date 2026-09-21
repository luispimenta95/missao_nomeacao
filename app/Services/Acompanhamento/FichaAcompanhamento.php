<?php

namespace App\Services\Acompanhamento;

use App\Enums\AcaoAcompanhamento;
use App\Enums\TipoMotivoAcompanhamento;

final class FichaAcompanhamento
{
    /**
     * @param  list<MotivoAcompanhamento>  $motivos
     * @param  list<EvolucaoParametro>  $evolucoes
     */
    public function __construct(
        public AcaoAcompanhamento $acao,
        public array $motivos,
        public array $evolucoes,
    ) {}

    public function motivoPrincipal(): ?MotivoAcompanhamento
    {
        foreach ($this->motivos as $motivo) {
            if ($motivo->acao === $this->acao) {
                return $motivo;
            }
        }

        return $this->motivos[0] ?? null;
    }

    public function tem(TipoMotivoAcompanhamento $tipo): bool
    {
        foreach ($this->motivos as $motivo) {
            if ($motivo->tipo === $tipo) {
                return true;
            }
        }

        return false;
    }

    public function assinatura(): string
    {
        $linhas = [$this->acao->value];
        foreach ($this->motivos as $motivo) {
            $linhas[] = $motivo->tipo->value.'|'.$motivo->texto;
        }

        return hash('sha256', implode("\n", $linhas));
    }
}
