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

        foreach ($assuntos as $item) {
            $pct = $this->paraNumero($item['percentual'] ?? null);
            $assunto = trim((string) ($item['assunto'] ?? ''));
            if ($pct === null || $assunto === '' || $pct > 75) {
                continue;
            }
            $bloco = $this->avaliarEixo(
                EixoDesempenho::ASSUNTO,
                $pct,
                [
                    'NOME' => $primeiroNome,
                    'FULANO' => $primeiroNome,
                    'ASSUNTO' => $assunto,
                    'DISCIPLINA' => trim((string) ($item['disciplina'] ?? '')),
                    'PERCENTUAL' => $this->fmtPct($pct),
                ]
            );
            if ($bloco !== null) {
                $bloco['meta'] = [
                    'disciplina' => $item['disciplina'] ?? null,
                    'assunto' => $assunto,
                    'percentual' => $pct,
                ];
                $blocos[] = $bloco;
            }
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
     * @param  array<string, string>  $vars
     * @return array{eixo: string, eixo_nome: string, faixa: string, faixa_nome: string, titulo: string, texto: string}|null
     */
    private function avaliarEixo(string $eixoCodigo, float $valor, array $vars): ?array
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
            'texto' => $this->aplicarPlaceholders($faixa->texto_email, $vars),
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
    public function aplicarPlaceholders(string $texto, array $vars): string
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

        return preg_replace('/\s+/u', ' ', trim($out)) ?? trim($out);
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
