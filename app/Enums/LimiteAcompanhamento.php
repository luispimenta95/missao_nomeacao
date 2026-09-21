<?php

namespace App\Enums;

use Carbon\Carbon;
use DateTimeInterface;

enum LimiteAcompanhamento: int
{
    case DiasSemContato = 15;

    public function excedido(int $dias): bool
    {
        return $dias > $this->value;
    }

    /**
     * 15º dia corrido, contando a data atual como o primeiro.
     */
    public function dataContandoHoje(DateTimeInterface $hoje): Carbon
    {
        return Carbon::parse($hoje->format('Y-m-d'))->startOfDay()->addDays($this->value - 1);
    }
}
