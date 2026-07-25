<?php

namespace App\Services\Desempenho;

use App\Models\EixoDesempenho;
use App\Models\FaixaDesempenho;

class AvaliadorDesempenho
{
    /**
     * Avalia o relatório completo conforme os eixos do documento de parâmetros.
     *
     * @param  array{
     *   nome?: string,
     *   dias_analisados?: int|float|null,
     *   dias_estudados?: int|float|null,
     *   dias_falhados?: int|float|null,
     *   total_questoes?: int|float|null,
     *   percentual_acertos?: int|float|null,
     *   assuntos?: list<array{disciplina?: string, assunto: string, percentual: float|int|null}>
     * }  $dados
     * @return array{
     *   blocos: list<array{eixo: string, eixo_nome: string, faixa: string, faixa_nome: string, titulo: string, texto: string, meta?: array<string, mixed>}>,
     *   metricas: array<string, mixed>,
     *   resumo: string|null
     * }
     */
    public function avaliarRelatorio(array $dados): array
    {
        $nome = trim((string) ($dados['nome'] ?? 'Aluno'));
        $primeiroNome = $this->primeiroNome($nome);

        $diasAnalisados = $this->paraNumero($dados['dias_analisados'] ?? null);
        $diasEstudados = $this->paraNumero($dados['dias_estudados'] ?? null);
        $diasFalhados = $this->paraNumero($dados['dias_falhados'] ?? null);
        if ($diasFalhados === null && $diasAnalisados !== null && $diasEstudados !== null) {
            $diasFalhados = max(0, $diasAnalisados - $diasEstudados);
        }

        $totalQuestoes = $this->paraNumero($dados['total_questoes'] ?? null);
        $percentualAcertos = $this->paraNumero($dados['percentual_acertos'] ?? null);
        $assuntos = is_array($dados['assuntos'] ?? null) ? $dados['assuntos'] : [];

        $metricas = [
            'dias_analisados' => $diasAnalisados,
            'dias_estudados' => $diasEstudados,
            'dias_falhados' => $diasFalhados,
            'total_questoes' => $totalQuestoes,
            'percentual_acertos' => $percentualAcertos,
            'assuntos_avaliados' => count($assuntos),
        ];

        $blocos = [];

        if ($diasFalhados !== null) {
            $bloco = $this->avaliarEixo(
                EixoDesempenho::CONSTANCIA,
                $diasFalhados,
                [
                    'NOME' => $primeiroNome,
                    'FULANO' => $primeiroNome,
                    'X' => $this->fmtInt($diasEstudados),
                    'Y' => $this->fmtInt($diasAnalisados),
                    'Z' => $this->fmtInt($diasFalhados),
                    'DIAS_ESTUDADOS' => $this->fmtInt($diasEstudados),
                    'DIAS_ANALISADOS' => $this->fmtInt($diasAnalisados),
                    'DIAS_FALHADOS' => $this->fmtInt($diasFalhados),
                ]
            );
            if ($bloco !== null) {
                $blocos[] = $bloco;
            }
        }

        if ($totalQuestoes !== null) {
            $bloco = $this->avaliarEixo(
                EixoDesempenho::VOLUME_QUESTOES,
                $totalQuestoes,
                [
                    'NOME' => $primeiroNome,
                    'FULANO' => $primeiroNome,
                    'TOTAL_QUESTOES' => $this->fmtInt($totalQuestoes),
                    'X' => $this->fmtInt($totalQuestoes),
                    'X_QUESTOES' => $this->fmtInt($totalQuestoes).' questões',
                ]
            );
            if ($bloco !== null) {
                $blocos[] = $bloco;
            }
        }

        // Percentual geral só com amostra >= 100
        if ($totalQuestoes !== null && $totalQuestoes >= 100 && $percentualAcertos !== null) {
            $bloco = $this->avaliarEixo(
                EixoDesempenho::PERCENTUAL_ACERTOS,
                $percentualAcertos,
                [
                    'NOME' => $primeiroNome,
                    'FULANO' => $primeiroNome,
                    'PERCENTUAL_ACERTOS' => $this->fmtPct($percentualAcertos),
                    'TOTAL_QUESTOES' => $this->fmtInt($totalQuestoes),
                ]
            );
            if ($bloco !== null) {
                $blocos[] = $bloco;
            }
        }

        $blocoAssuntos = $this->avaliarAssuntos($assuntos, $primeiroNome);
        if ($blocoAssuntos !== null) {
            $blocos[] = $blocoAssuntos;
        }

        $resumo = null;
        foreach ($blocos as $bloco) {
            if (($bloco['eixo'] ?? '') === EixoDesempenho::CONSTANCIA) {
                $resumo = $bloco['faixa_nome'];
                break;
            }
        }

        return [
            'blocos' => $blocos,
            'metricas' => $metricas,
            'resumo' => $resumo,
        ];
    }

    /**
     * Um único bloco com N bullets para assuntos ≤ 75%.
     *
     * @param  list<array{disciplina?: string, assunto: string, percentual: float|int|null}>  $assuntos
     * @return array{eixo: string, eixo_nome: string, faixa: string, faixa_nome: string, titulo: string, texto: string, itens: list<string>, meta?: array<string, mixed>}|null
     */
    private function avaliarAssuntos(array $assuntos, string $primeiroNome): ?array
    {
        $itens = [];
        $piorPct = null;

        foreach ($assuntos as $item) {
            $pct = $this->paraNumero($item['percentual'] ?? null);
            $assunto = trim((string) ($item['assunto'] ?? ''));
            if ($pct === null || $assunto === '' || $pct > 75) {
                continue;
            }
            $itens[] = [
                'disciplina' => trim((string) ($item['disciplina'] ?? '')),
                'assunto' => $assunto,
                'percentual' => $pct,
                'linha' => 'No assunto '.$assunto.', você alcançou '.$this->fmtPct($pct).'% de acertos.',
            ];
            if ($piorPct === null || $pct < $piorPct) {
                $piorPct = $pct;
            }
        }

        if ($itens === [] || $piorPct === null) {
            return null;
        }

        $lista = implode("\n", array_map(
            static fn (array $i): string => '• '.$i['linha'],
            $itens
        ));

        $bloco = $this->avaliarEixo(
            EixoDesempenho::ASSUNTO,
            $piorPct,
            [
                'NOME' => $primeiroNome,
                'FULANO' => $primeiroNome,
                'LISTA_ASSUNTOS' => $lista,
            ],
            preserveNewlines: true
        );

        if ($bloco === null) {
            return null;
        }

        $bloco['itens'] = array_values(array_map(
            static fn (array $i): string => $i['linha'],
            $itens
        ));
        $bloco['meta'] = [
            'quantidade' => count($itens),
            'pior_percentual' => $piorPct,
        ];

        return $bloco;
    }

    /**
     * @param  array<string, string>  $vars
     * @return array{eixo: string, eixo_nome: string, faixa: string, faixa_nome: string, titulo: string, texto: string}|null
     */
    private function avaliarEixo(string $eixoCodigo, float $valor, array $vars, bool $preserveNewlines = false): ?array
    {
        $eixo = EixoDesempenho::query()
            ->where('codigo', $eixoCodigo)
            ->where('ativo', true)
            ->with(['faixas' => static fn ($q) => $q->where('ativo', true)->orderBy('ordem')])
            ->first();

        if ($eixo === null) {
            return null;
        }

        $faixa = $this->encontrarFaixa($eixo->faixas, $valor);
        if ($faixa === null) {
            return null;
        }

        return [
            'eixo' => $eixo->codigo,
            'eixo_nome' => $eixo->nome,
            'faixa' => $faixa->codigo,
            'faixa_nome' => $faixa->nome,
            'titulo' => $eixo->nome.': '.$faixa->nome,
            'texto' => $this->aplicarPlaceholders($faixa->texto_email, $vars, $preserveNewlines),
        ];
    }

    /**
     * @param  iterable<int, FaixaDesempenho>  $faixas
     */
    private function encontrarFaixa(iterable $faixas, float $valor): ?FaixaDesempenho
    {
        foreach ($faixas as $faixa) {
            if ($faixa->contemValor($valor)) {
                return $faixa;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $vars
     */
    public function aplicarPlaceholders(string $texto, array $vars, bool $preserveNewlines = false): string
    {
        $out = $texto;
        foreach ($vars as $chave => $valor) {
            $out = str_replace(
                ['{'.$chave.'}', '['.$chave.']', '{'.$chave.'_QUESTOES}'],
                [$valor, $valor, $valor],
                $out
            );
        }

        // Variantes do documento
        $out = str_replace(
            ['[Fulano]', '[FULANO]', '{Fulano}', '[X questões]', '{X questões}'],
            [
                $vars['FULANO'] ?? $vars['NOME'] ?? 'Aluno',
                $vars['FULANO'] ?? $vars['NOME'] ?? 'Aluno',
                $vars['FULANO'] ?? $vars['NOME'] ?? 'Aluno',
                $vars['X_QUESTOES'] ?? (($vars['TOTAL_QUESTOES'] ?? '0').' questões'),
                $vars['X_QUESTOES'] ?? (($vars['TOTAL_QUESTOES'] ?? '0').' questões'),
            ],
            $out
        );

        $out = trim($out);
        if ($preserveNewlines) {
            // Normaliza só espaços horizontais; mantém quebras de linha da lista
            $out = preg_replace('/[^\S\n]+/u', ' ', $out) ?? $out;
            $out = preg_replace("/\n{3,}/u", "\n\n", $out) ?? $out;

            return trim($out);
        }

        return preg_replace('/\s+/u', ' ', $out) ?? $out;
    }

    public function paraNumero(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        if (preg_match('/^(\d+):(\d{1,2})$/', $texto, $m)) {
            return ((int) $m[1]) + (((int) $m[2]) / 60);
        }

        $texto = str_replace(['%', ' '], '', $texto);
        $texto = str_replace(',', '.', $texto);
        if (! is_numeric($texto)) {
            return null;
        }

        return (float) $texto;
    }

    private function primeiroNome(string $nome): string
    {
        $partes = preg_split('/\s+/u', trim($nome)) ?: [];
        $primeiro = $partes[0] ?? 'Aluno';

        return $primeiro !== '' ? $primeiro : 'Aluno';
    }

    private function fmtInt(?float $n): string
    {
        if ($n === null) {
            return '0';
        }

        return (string) (int) round($n);
    }

    private function fmtPct(?float $n): string
    {
        if ($n === null) {
            return '0';
        }
        if (abs($n - round($n)) < 0.05) {
            return (string) (int) round($n);
        }

        return number_format($n, 1, ',', '');
    }
}
