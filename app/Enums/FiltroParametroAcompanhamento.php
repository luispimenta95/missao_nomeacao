<?php

namespace App\Enums;

enum FiltroParametroAcompanhamento: string
{
    case Constancia = 'constancia';
    case VolumeQuestoes = 'volume_questoes';
    case DesempenhoQuestoes = 'percentual_acertos';
    case Assunto = 'assunto';
    case Contato = 'contato';

    public function rotulo(): string
    {
        return match ($this) {
            self::Constancia => 'Constância',
            self::VolumeQuestoes => 'Volume de questões',
            self::DesempenhoQuestoes => 'Desempenho em questões',
            self::Assunto => 'Assunto',
            self::Contato => 'Contato',
        };
    }
}
