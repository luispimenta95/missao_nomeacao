<?php

namespace App\Enums;

enum ParametroAcompanhamento: string
{
    case Constancia = 'constancia';
    case VolumeQuestoes = 'volume_questoes';
    case DesempenhoQuestoes = 'percentual_acertos';

    public function rotulo(): string
    {
        return match ($this) {
            self::Constancia => 'Constância',
            self::VolumeQuestoes => 'Volume de questões',
            self::DesempenhoQuestoes => 'Desempenho em questões',
        };
    }

    public function filtro(): FiltroParametroAcompanhamento
    {
        return match ($this) {
            self::Constancia => FiltroParametroAcompanhamento::Constancia,
            self::VolumeQuestoes => FiltroParametroAcompanhamento::VolumeQuestoes,
            self::DesempenhoQuestoes => FiltroParametroAcompanhamento::DesempenhoQuestoes,
        };
    }
}
