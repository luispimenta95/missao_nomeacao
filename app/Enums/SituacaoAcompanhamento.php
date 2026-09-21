<?php

namespace App\Enums;

enum SituacaoAcompanhamento: string
{
    case Pendente = 'pendente';
    case Concluida = 'concluida';

    public function rotulo(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Concluida => 'Concluída',
        };
    }
}
