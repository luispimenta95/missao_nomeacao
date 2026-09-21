<?php

namespace App\Enums;

enum AcaoAcompanhamento: string
{
    case Intervir = 'intervir';
    case MarcarPresenca = 'marcar_presenca';
    case Parabenizar = 'parabenizar';

    public function rotulo(): string
    {
        return match ($this) {
            self::Intervir => 'Intervir',
            self::MarcarPresenca => 'Marcar presença',
            self::Parabenizar => 'Parabenizar',
        };
    }

    /**
     * Intervir > Marcar presença > Parabenizar.
     */
    public function prioridade(): int
    {
        return match ($this) {
            self::Intervir => 3,
            self::MarcarPresenca => 2,
            self::Parabenizar => 1,
        };
    }
}
