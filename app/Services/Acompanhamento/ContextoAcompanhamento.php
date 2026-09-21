<?php

namespace App\Services\Acompanhamento;

use App\Enums\ParametroAcompanhamento;
use App\Models\Aluno;
use App\Models\EixoDesempenho;
use DateTimeInterface;

final class ContextoAcompanhamento
{
    /**
     * @param  list<array{disciplina: string, assunto: string, faixa: string, faixa_nome: string, percentual: float|null}>  $assuntos
     */
    public function __construct(
        public ?string $constanciaAtual,
        public ?string $constanciaAnterior,
        public ?string $constanciaAtualNome,
        public ?string $constanciaAnteriorNome,
        public ?string $volumeAtual,
        public ?string $volumeAnterior,
        public ?string $volumeAtualNome,
        public ?string $volumeAnteriorNome,
        public ?string $desempenhoAtual,
        public ?string $desempenhoAnterior,
        public ?string $desempenhoAtualNome,
        public ?string $desempenhoAnteriorNome,
        public array $assuntos,
        public int $diasSemContato,
        public bool $contatoProgramadoHoje,
    ) {}

    public static function fromAluno(Aluno $aluno, DateTimeInterface $hoje): self
    {
        $constancia = self::par(
            EixoDesempenho::CONSTANCIA,
            $aluno->last_performance_codigo,
            $aluno->last_performance,
            $aluno->prev_performance_codigo,
            $aluno->prev_performance,
        );
        $volume = self::par(
            EixoDesempenho::VOLUME_QUESTOES,
            $aluno->last_question_volume_codigo,
            $aluno->last_question_volume,
            $aluno->prev_question_volume_codigo,
            $aluno->prev_question_volume,
        );
        $desempenho = self::par(
            EixoDesempenho::PERCENTUAL_ACERTOS,
            $aluno->last_accuracy_rate_codigo,
            $aluno->last_accuracy_rate,
            $aluno->prev_accuracy_rate_codigo,
            $aluno->prev_accuracy_rate,
        );

        $referencia = $aluno->ultimo_contato_em ?? $aluno->created_at;
        $proximo = $aluno->proximo_contato_em;

        return new self(
            constanciaAtual: $constancia['atual'],
            constanciaAnterior: $constancia['anterior'],
            constanciaAtualNome: $constancia['atual_nome'],
            constanciaAnteriorNome: $constancia['anterior_nome'],
            volumeAtual: $volume['atual'],
            volumeAnterior: $volume['anterior'],
            volumeAtualNome: $volume['atual_nome'],
            volumeAnteriorNome: $volume['anterior_nome'],
            desempenhoAtual: $desempenho['atual'],
            desempenhoAnterior: $desempenho['anterior'],
            desempenhoAtualNome: $desempenho['atual_nome'],
            desempenhoAnteriorNome: $desempenho['anterior_nome'],
            assuntos: self::assuntos($aluno),
            diasSemContato: self::diasDesde($referencia, $hoje),
            contatoProgramadoHoje: $proximo !== null && $proximo->toDateString() === self::dia($hoje),
        );
    }

    public static function diasDesde(?DateTimeInterface $referencia, DateTimeInterface $hoje): int
    {
        if ($referencia === null) {
            return 0;
        }

        $inicioRef = strtotime(self::dia($referencia).' 00:00:00');
        $inicioHoje = strtotime(self::dia($hoje).' 00:00:00');
        if ($inicioRef === false || $inicioHoje === false) {
            return 0;
        }

        return max(0, (int) floor(($inicioHoje - $inicioRef) / 86400));
    }

    public function nomeAtual(ParametroAcompanhamento $parametro): ?string
    {
        return match ($parametro) {
            ParametroAcompanhamento::Constancia => $this->constanciaAtualNome,
            ParametroAcompanhamento::VolumeQuestoes => $this->volumeAtualNome,
            ParametroAcompanhamento::DesempenhoQuestoes => $this->desempenhoAtualNome,
        };
    }

    public function nomeAnterior(ParametroAcompanhamento $parametro): ?string
    {
        return match ($parametro) {
            ParametroAcompanhamento::Constancia => $this->constanciaAnteriorNome,
            ParametroAcompanhamento::VolumeQuestoes => $this->volumeAnteriorNome,
            ParametroAcompanhamento::DesempenhoQuestoes => $this->desempenhoAnteriorNome,
        };
    }

    public function codigoAtual(ParametroAcompanhamento $parametro): ?string
    {
        return match ($parametro) {
            ParametroAcompanhamento::Constancia => $this->constanciaAtual,
            ParametroAcompanhamento::VolumeQuestoes => $this->volumeAtual,
            ParametroAcompanhamento::DesempenhoQuestoes => $this->desempenhoAtual,
        };
    }

    public function codigoAnterior(ParametroAcompanhamento $parametro): ?string
    {
        return match ($parametro) {
            ParametroAcompanhamento::Constancia => $this->constanciaAnterior,
            ParametroAcompanhamento::VolumeQuestoes => $this->volumeAnterior,
            ParametroAcompanhamento::DesempenhoQuestoes => $this->desempenhoAnterior,
        };
    }

    /**
     * @return array{atual: ?string, anterior: ?string, atual_nome: ?string, anterior_nome: ?string}
     */
    private static function par(string $eixo, ?string $codigoAtual, ?string $nomeAtual, ?string $codigoAnterior, ?string $nomeAnterior): array
    {
        $atual = CatalogoFaixas::resolverCodigo($eixo, $codigoAtual, $nomeAtual);
        $anterior = CatalogoFaixas::resolverCodigo($eixo, $codigoAnterior, $nomeAnterior);

        return [
            'atual' => $atual,
            'anterior' => $anterior,
            'atual_nome' => CatalogoFaixas::resolverNome($eixo, $atual, $nomeAtual),
            'anterior_nome' => CatalogoFaixas::resolverNome($eixo, $anterior, $nomeAnterior),
        ];
    }

    /**
     * @return list<array{disciplina: string, assunto: string, faixa: string, faixa_nome: string, percentual: float|null}>
     */
    private static function assuntos(Aluno $aluno): array
    {
        $itens = is_array($aluno->assuntos_detalhe) ? $aluno->assuntos_detalhe : [];
        $lista = [];
        foreach ($itens as $item) {
            if (! is_array($item)) {
                continue;
            }
            $assunto = trim((string) ($item['assunto'] ?? ''));
            if ($assunto === '') {
                continue;
            }
            $faixa = CatalogoFaixas::resolverCodigo(
                EixoDesempenho::ASSUNTO,
                isset($item['faixa']) ? (string) $item['faixa'] : null,
                isset($item['faixa_nome']) ? (string) $item['faixa_nome'] : null,
            );
            if ($faixa === null) {
                continue;
            }
            $percentual = $item['percentual'] ?? null;
            $lista[] = [
                'disciplina' => trim((string) ($item['disciplina'] ?? '')),
                'assunto' => $assunto,
                'faixa' => $faixa,
                'faixa_nome' => CatalogoFaixas::resolverNome(
                    EixoDesempenho::ASSUNTO,
                    $faixa,
                    isset($item['faixa_nome']) ? (string) $item['faixa_nome'] : null,
                ) ?? $faixa,
                'percentual' => is_numeric($percentual) ? (float) $percentual : null,
            ];
        }

        usort($lista, static function (array $a, array $b): int {
            return ($a['percentual'] ?? 999) <=> ($b['percentual'] ?? 999);
        });

        return $lista;
    }

    private static function dia(DateTimeInterface $data): string
    {
        return $data->format('Y-m-d');
    }
}
