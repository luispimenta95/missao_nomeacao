<?php

namespace App\Enums;

/**
 * Papel da faixa na regra de acompanhamento.
 * Critica dispara Intervir. Baixa, no desempenho ou no assunto, dispara Marcar presença.
 */
enum PapelFaixa: string
{
    case Critica = 'critica';
    case Baixa = 'baixa';
    case Neutra = 'neutra';
    case Alta = 'alta';
}
