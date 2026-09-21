<?php

namespace App\Services\Acompanhamento;

use App\Enums\ParametroAcompanhamento;
use App\Enums\TendenciaFaixa;

final class EvolucaoParametro
{
    public function __construct(
        public ParametroAcompanhamento $parametro,
        public ?string $anterior,
        public ?string $atual,
        public TendenciaFaixa $tendencia,
    ) {}
}
