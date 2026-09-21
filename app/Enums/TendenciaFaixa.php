<?php

namespace App\Enums;

enum TendenciaFaixa: string
{
    case Melhorou = 'melhorou';
    case Manteve = 'manteve';
    case Piorou = 'piorou';
    case Indefinida = 'indefinida';

    public function simbolo(): string
    {
        return match ($this) {
            self::Melhorou => '↑',
            self::Piorou => '↓',
            self::Manteve => '→',
            self::Indefinida => '·',
        };
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Melhorou => 'Evoluiu',
            self::Piorou => 'Piorou',
            self::Manteve => 'Manteve',
            self::Indefinida => 'Sem comparação',
        };
    }
}
