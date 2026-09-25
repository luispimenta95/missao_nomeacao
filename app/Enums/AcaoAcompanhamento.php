<?php

namespace App\Enums;

enum AcaoAcompanhamento: string
{
    case Intervir = 'intervir';
    case MarcarPresenca = 'marcar_presenca';
    case Parabenizar = 'parabenizar';
    case Ok = 'ok';

    public function rotulo(): string
    {
        return match ($this) {
            self::Intervir => 'Intervir',
            self::MarcarPresenca => 'Marcar presença',
            self::Parabenizar => 'Parabenizar',
            self::Ok => 'Ok',
        };
    }

    /**
     * Intervir > Marcar presença > Parabenizar > Ok.
     */
    public function prioridade(): int
    {
        return match ($this) {
            self::Intervir => 3,
            self::MarcarPresenca => 2,
            self::Parabenizar => 1,
            self::Ok => 0,
        };
    }
}
