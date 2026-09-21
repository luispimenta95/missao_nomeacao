<?php

namespace App\Services\Acompanhamento;

use App\Enums\AcaoAcompanhamento;
use App\Enums\FiltroParametroAcompanhamento;
use App\Enums\TipoMotivoAcompanhamento;

final class MotivoAcompanhamento
{
    public function __construct(
        public TipoMotivoAcompanhamento $tipo,
        public AcaoAcompanhamento $acao,
        public string $texto,
        public bool $ponteProtocoloResgate,
        public ?FiltroParametroAcompanhamento $filtro,
        public int $ordem,
    ) {}
}
