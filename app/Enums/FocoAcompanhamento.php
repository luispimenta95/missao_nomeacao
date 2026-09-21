<?php

namespace App\Enums;

enum FocoAcompanhamento: string
{
    case Intervir = 'intervir';
    case AgendaHoje = 'agenda_hoje';
    case Agenda = 'agenda';
    case SemContato = 'sem_contato';
    case Parabenizar = 'parabenizar';

    public function rotulo(): string
    {
        return match ($this) {
            self::Intervir => 'Intervenções pendentes',
            self::AgendaHoje => 'Acompanhamento programado para hoje',
            self::Agenda => 'Agenda de acompanhamentos',
            self::SemContato => 'Alunos sem contato',
            self::Parabenizar => 'Evoluções ainda não reconhecidas',
        };
    }
}
