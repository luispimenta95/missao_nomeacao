<?php

namespace App\Enums;

enum TipoMotivoAcompanhamento: string
{
    case ConstanciaCritica = 'constancia_critica';
    case VolumeCritico = 'volume_critico';
    case DesempenhoBaixo = 'desempenho_baixo';
    case AssuntoBaixo = 'assunto_baixo';
    case SemContato = 'sem_contato';
    case ContatoProgramado = 'contato_programado';
    case ContatoAgendado = 'contato_agendado';
    case Evolucao = 'evolucao';
    case Panorama = 'panorama';

    public function acao(): AcaoAcompanhamento
    {
        return match ($this) {
            self::ConstanciaCritica, self::VolumeCritico => AcaoAcompanhamento::Intervir,
            self::Evolucao => AcaoAcompanhamento::Parabenizar,
            self::Panorama => AcaoAcompanhamento::EmDia,
            default => AcaoAcompanhamento::MarcarPresenca,
        };
    }
}
