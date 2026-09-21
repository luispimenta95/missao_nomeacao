<?php

namespace App\Services\Acompanhamento;

use App\Enums\PapelFaixa;
use App\Enums\ParametroAcompanhamento;
use App\Enums\TendenciaFaixa;
use App\Models\EixoDesempenho;

/**
 * Faixas já cadastradas na base, com o papel de cada uma na regra de ação.
 *
 * Volume: só "Crítico e inconclusivo" (crítico e insuficiente no texto do parâmetro)
 * dispara Intervir. "Volume baixo" não entra.
 * Desempenho em questões baixo = faixas abaixo de Mediano (Crítico e Alerta).
 * Assunto baixo = Crítico e Abaixo da média, o recorte de até 75% da base.
 */
final class CatalogoFaixas
{
    public const CONSTANCIA_CRITICA = 'critico';

    public const VOLUME_CRITICO = 'critico_inconclusivo';

    /**
     * Quanto maior, melhor a faixa.
     *
     * @var array<string, array<string, int>>
     */
    private const RANK = [
        EixoDesempenho::CONSTANCIA => [
            'excelente' => 4,
            'bom' => 3,
            'brigando' => 2,
            'critico' => 1,
        ],
        EixoDesempenho::VOLUME_QUESTOES => [
            'volume_alto' => 4,
            'volume_suficiente' => 3,
            'volume_baixo' => 2,
            'critico_inconclusivo' => 1,
        ],
        EixoDesempenho::PERCENTUAL_ACERTOS => [
            'excelente' => 5,
            'muito_bom' => 4,
            'mediano' => 3,
            'alerta' => 2,
            'critico' => 1,
        ],
    ];

    /**
     * @var array<string, array<string, PapelFaixa>>
     */
    private const PAPEL = [
        EixoDesempenho::CONSTANCIA => [
            'excelente' => PapelFaixa::Alta,
            'bom' => PapelFaixa::Alta,
            'brigando' => PapelFaixa::Neutra,
            'critico' => PapelFaixa::Critica,
        ],
        EixoDesempenho::VOLUME_QUESTOES => [
            'volume_alto' => PapelFaixa::Alta,
            'volume_suficiente' => PapelFaixa::Neutra,
            'volume_baixo' => PapelFaixa::Baixa,
            'critico_inconclusivo' => PapelFaixa::Critica,
        ],
        EixoDesempenho::PERCENTUAL_ACERTOS => [
            'excelente' => PapelFaixa::Alta,
            'muito_bom' => PapelFaixa::Alta,
            'mediano' => PapelFaixa::Neutra,
            'alerta' => PapelFaixa::Baixa,
            'critico' => PapelFaixa::Baixa,
        ],
        EixoDesempenho::ASSUNTO => [
            'critico' => PapelFaixa::Baixa,
            'abaixo_media' => PapelFaixa::Baixa,
        ],
    ];

    /**
     * Nome exibido na base → código, por eixo.
     *
     * @var array<string, array<string, string>>
     */
    private const POR_NOME = [
        EixoDesempenho::CONSTANCIA => [
            'excelente' => 'excelente',
            'bom' => 'bom',
            'brigando com a constância' => 'brigando',
            'crítico' => 'critico',
        ],
        EixoDesempenho::VOLUME_QUESTOES => [
            'crítico e inconclusivo' => 'critico_inconclusivo',
            'volume baixo' => 'volume_baixo',
            'volume suficiente' => 'volume_suficiente',
            'volume alto' => 'volume_alto',
        ],
        EixoDesempenho::PERCENTUAL_ACERTOS => [
            'crítico' => 'critico',
            'alerta' => 'alerta',
            'mediano' => 'mediano',
            'muito bom' => 'muito_bom',
            'excelente' => 'excelente',
        ],
        EixoDesempenho::ASSUNTO => [
            'crítico' => 'critico',
            'abaixo da média' => 'abaixo_media',
        ],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    private const NOME_PADRAO = [
        EixoDesempenho::CONSTANCIA => [
            'excelente' => 'Excelente',
            'bom' => 'Bom',
            'brigando' => 'Brigando com a constância',
            'critico' => 'Crítico',
        ],
        EixoDesempenho::VOLUME_QUESTOES => [
            'critico_inconclusivo' => 'Crítico e inconclusivo',
            'volume_baixo' => 'Volume baixo',
            'volume_suficiente' => 'Volume suficiente',
            'volume_alto' => 'Volume alto',
        ],
        EixoDesempenho::PERCENTUAL_ACERTOS => [
            'critico' => 'Crítico',
            'alerta' => 'Alerta',
            'mediano' => 'Mediano',
            'muito_bom' => 'Muito bom',
            'excelente' => 'Excelente',
        ],
        EixoDesempenho::ASSUNTO => [
            'critico' => 'Crítico',
            'abaixo_media' => 'Abaixo da média',
        ],
    ];

    public static function conhecido(string $eixo, ?string $codigo): bool
    {
        return self::rank($eixo, $codigo) !== null || self::papel($eixo, $codigo) !== null;
    }

    public static function rank(string $eixo, ?string $codigo): ?int
    {
        $codigo = self::limpar($codigo);
        if ($codigo === null) {
            return null;
        }

        return self::RANK[$eixo][$codigo] ?? null;
    }

    public static function papel(string $eixo, ?string $codigo): ?PapelFaixa
    {
        $codigo = self::limpar($codigo);
        if ($codigo === null) {
            return null;
        }

        return self::PAPEL[$eixo][$codigo] ?? null;
    }

    public static function codigoPorNome(string $eixo, ?string $nome): ?string
    {
        $nome = self::limpar($nome);
        if ($nome === null) {
            return null;
        }

        return self::POR_NOME[$eixo][$nome] ?? null;
    }

    public static function nomePadrao(string $eixo, ?string $codigo): ?string
    {
        $codigo = self::limpar($codigo);
        if ($codigo === null) {
            return null;
        }

        return self::NOME_PADRAO[$eixo][$codigo] ?? null;
    }

    public static function resolverCodigo(string $eixo, ?string $codigo, ?string $nome): ?string
    {
        $codigo = self::limpar($codigo);
        if ($codigo !== null && self::conhecido($eixo, $codigo)) {
            return $codigo;
        }

        return self::codigoPorNome($eixo, $nome);
    }

    public static function resolverNome(string $eixo, ?string $codigo, ?string $nome): ?string
    {
        $nome = trim((string) $nome);
        if ($nome !== '') {
            return $nome;
        }

        return self::nomePadrao($eixo, $codigo);
    }

    public static function tendencia(ParametroAcompanhamento $parametro, ?string $anterior, ?string $atual): TendenciaFaixa
    {
        $de = self::rank($parametro->value, $anterior);
        $para = self::rank($parametro->value, $atual);
        if ($de === null || $para === null) {
            return TendenciaFaixa::Indefinida;
        }
        if ($para > $de) {
            return TendenciaFaixa::Melhorou;
        }
        if ($para < $de) {
            return TendenciaFaixa::Piorou;
        }

        return TendenciaFaixa::Manteve;
    }

    private static function limpar(?string $valor): ?string
    {
        $valor = mb_strtolower(trim((string) $valor));

        return $valor === '' ? null : $valor;
    }
}
