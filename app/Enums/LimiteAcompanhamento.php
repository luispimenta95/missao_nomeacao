<?php

namespace App\Enums;

enum LimiteAcompanhamento: int
{
    case DiasSemContato = 15;

    public function excedido(int $dias): bool
    {
        return $dias > $this->value;
    }
}
