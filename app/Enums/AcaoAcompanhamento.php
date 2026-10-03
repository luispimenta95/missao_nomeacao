<?php

namespace App\Enums;

enum AcaoAcompanhamento: string
{
    case Intervir = 'intervir';
    case MarcarPresenca = 'marcar_presenca';
    case Parabenizar = 'parabenizar';
    case Ok = 'ok';
    case RestabelecerContato = 'restabelecer_contato';
    case AlunoNovato = 'aluno_novato';

    public function rotulo(): string
    {
        return match ($this) {
            self::Intervir => 'Intervir',
            self::MarcarPresenca => 'Marcar presença',
            self::Parabenizar => 'Parabenizar',
            self::Ok => 'Ok',
            self::RestabelecerContato => 'Restabelecer contato',
            self::AlunoNovato => 'Aluno novato',
        };
    }

    /**
     * Intervir > Marcar presença > Parabenizar > Ok.
     * Restabelecer contato e Aluno novato ficam fora dessa ordem: o montador
     * atribui essas ações sem disputar com os motivos de desempenho.
     */
    public function prioridade(): int
    {
        return match ($this) {
            self::Intervir => 3,
            self::MarcarPresenca => 2,
            self::Parabenizar => 1,
            self::Ok, self::RestabelecerContato, self::AlunoNovato => 0,
        };
    }
}
