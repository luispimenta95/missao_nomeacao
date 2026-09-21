<?php

namespace App\Enums;

enum FiltroSituacaoAcompanhamento: string
{
    case Pendentes = 'pendentes';
    case Concluidas = 'concluidas';
    case Todas = 'todas';

    public function rotulo(): string
    {
        return match ($this) {
            self::Pendentes => 'Pendentes',
            self::Concluidas => 'Concluídas',
            self::Todas => 'Todas',
        };
    }

    public function aceita(SituacaoAcompanhamento $situacao): bool
    {
        return match ($this) {
            self::Pendentes => $situacao === SituacaoAcompanhamento::Pendente,
            self::Concluidas => $situacao === SituacaoAcompanhamento::Concluida,
            self::Todas => true,
        };
    }
}
