<?php

namespace App\Models;

use App\Enums\AcaoAcompanhamento;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aluno extends Model
{
    use HasFactory;

    protected $table = 'alunos';

    protected $fillable = [
        'tutory_id',
        'nome',
        'email',
        'recebe_email',
        'ativo',
        'last_performance',
        'last_performance_codigo',
        'last_question_volume',
        'last_question_volume_codigo',
        'last_accuracy_rate',
        'last_accuracy_rate_codigo',
        'last_subjects',
        'prev_performance',
        'prev_performance_codigo',
        'prev_question_volume',
        'prev_question_volume_codigo',
        'prev_accuracy_rate',
        'prev_accuracy_rate_codigo',
        'metricas_periodo',
        'assuntos_detalhe',
        'ultimo_contato_em',
        'ultima_observacao',
        'proximo_contato_em',
        'acao_resolvida',
        'acao_resolvida_assinatura',
    ];

    protected $casts = [
        'recebe_email' => 'boolean',
        'ativo' => 'boolean',
        'assuntos_detalhe' => 'array',
        'ultimo_contato_em' => 'datetime',
        'proximo_contato_em' => 'date',
    ];

    public static function normalizarNome(string $nome): string
    {
        $limpo = trim(preg_replace('/\s+/u', ' ', $nome) ?? '');

        return mb_strtolower($limpo);
    }

    public static function encontrarPorTutoryId(string $tutoryId): ?self
    {
        $tutoryId = trim($tutoryId);
        if ($tutoryId === '') {
            return null;
        }

        return self::query()->where('tutory_id', $tutoryId)->first();
    }

    public static function encontrarPorEmail(string $email): ?self
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        return self::query()->whereRaw('lower(email) = ?', [$email])->first();
    }

    public static function encontrarPorNome(string $nome): ?self
    {
        $chave = self::normalizarNome($nome);
        if ($chave === '') {
            return null;
        }

        return self::query()->get()->first(
            static fn (self $aluno): bool => self::normalizarNome($aluno->nome) === $chave
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeComNomeParecido($query, ?string $busca)
    {
        $busca = trim((string) $busca);
        if ($busca === '') {
            return $query;
        }

        $like = '%'.addcslashes($busca, '%_\\').'%';

        return $query->whereRaw('nome LIKE ? ESCAPE ?', [$like, '\\']);
    }

    public function contatos(): HasMany
    {
        return $this->hasMany(ContatoAluno::class)->orderByDesc('ocorrido_em');
    }

    protected function acaoResolvida(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): ?AcaoAcompanhamento {
                if (! is_string($value) || $value === '') {
                    return null;
                }
                if ($value === 'em_dia') {
                    $value = AcaoAcompanhamento::Ok->value;
                }

                return AcaoAcompanhamento::tryFrom($value);
            },
            set: function (mixed $value): ?string {
                if ($value instanceof AcaoAcompanhamento) {
                    return $value->value;
                }
                if (! is_string($value) || $value === '') {
                    return null;
                }

                return $value === 'em_dia' ? AcaoAcompanhamento::Ok->value : $value;
            },
        );
    }

    /**
     * Grava as faixas do último relatório (constância, volume, % acertos, assuntos).
     * Um período novo preserva a faixa anterior para a comparação de Parabenizar.
     *
     * @param  array{blocos?: list<array<string, mixed>>, metricas?: array<string, mixed>, resumo?: string|null}  $avaliacao
     */
    public function aplicarAvaliacaoDesempenho(array $avaliacao, ?string $periodo = null): void
    {
        $porEixo = [];
        $porCodigo = [];
        $assuntos = [];
        $viuAssunto = false;

        foreach ($avaliacao['blocos'] ?? [] as $bloco) {
            if (! is_array($bloco)) {
                continue;
            }
            $eixo = (string) ($bloco['eixo'] ?? '');
            $nome = trim((string) ($bloco['faixa_nome'] ?? ''));
            $codigo = trim((string) ($bloco['faixa'] ?? ''));
            if ($eixo === '' || $nome === '') {
                continue;
            }
            $porEixo[$eixo][] = $nome;
            if ($codigo !== '') {
                $porCodigo[$eixo][] = $codigo;
            }
            if ($eixo === EixoDesempenho::ASSUNTO) {
                $viuAssunto = true;
                foreach ($bloco['assuntos'] ?? [] as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $assunto = trim((string) ($item['assunto'] ?? ''));
                    if ($assunto === '') {
                        continue;
                    }
                    $percentual = $item['percentual'] ?? null;
                    $assuntos[] = [
                        'disciplina' => trim((string) ($item['disciplina'] ?? '')),
                        'assunto' => $assunto,
                        'percentual' => is_numeric($percentual) ? (float) $percentual : null,
                        'faixa' => $codigo,
                        'faixa_nome' => $nome,
                    ];
                }
            }
        }

        $metricas = is_array($avaliacao['metricas'] ?? null) ? $avaliacao['metricas'] : [];
        $periodo = trim((string) $periodo);
        $periodo = $periodo === '' ? null : $periodo;
        $mesmoPeriodo = $periodo !== null && $periodo === $this->metricas_periodo;

        if ($periodo !== null && ! $mesmoPeriodo) {
            $this->acao_resolvida = null;
            $this->acao_resolvida_assinatura = null;
            if ($this->possuiFaixaRegistrada()) {
                $this->prev_performance = $this->last_performance;
                $this->prev_performance_codigo = $this->last_performance_codigo;
                $this->prev_question_volume = $this->last_question_volume;
                $this->prev_question_volume_codigo = $this->last_question_volume_codigo;
                $this->prev_accuracy_rate = $this->last_accuracy_rate;
                $this->prev_accuracy_rate_codigo = $this->last_accuracy_rate_codigo;
            }
        }

        if (isset($porEixo[EixoDesempenho::CONSTANCIA][0])) {
            $this->last_performance = $porEixo[EixoDesempenho::CONSTANCIA][0];
            $this->last_performance_codigo = $porCodigo[EixoDesempenho::CONSTANCIA][0] ?? null;
        }

        if (isset($porEixo[EixoDesempenho::VOLUME_QUESTOES][0])) {
            $this->last_question_volume = $porEixo[EixoDesempenho::VOLUME_QUESTOES][0];
            $this->last_question_volume_codigo = $porCodigo[EixoDesempenho::VOLUME_QUESTOES][0] ?? null;
        }

        if (isset($porEixo[EixoDesempenho::PERCENTUAL_ACERTOS][0])) {
            $this->last_accuracy_rate = $porEixo[EixoDesempenho::PERCENTUAL_ACERTOS][0];
            $this->last_accuracy_rate_codigo = $porCodigo[EixoDesempenho::PERCENTUAL_ACERTOS][0] ?? null;
        } else {
            $totalQuestoes = $metricas['total_questoes'] ?? null;
            if (is_numeric($totalQuestoes) && (float) $totalQuestoes < 100) {
                $this->last_accuracy_rate = null;
                $this->last_accuracy_rate_codigo = null;
            }
        }

        if (isset($porEixo[EixoDesempenho::ASSUNTO])) {
            $this->last_subjects = implode(' · ', array_values(array_unique($porEixo[EixoDesempenho::ASSUNTO])));
        } elseif ((int) ($metricas['assuntos_avaliados'] ?? 0) > 0) {
            $this->last_subjects = 'Sem pontos de atenção';
        }

        if ($viuAssunto || array_key_exists('assuntos_avaliados', $metricas)) {
            $this->assuntos_detalhe = $assuntos;
        }

        if ($periodo !== null) {
            $this->metricas_periodo = $periodo;
        }

        $this->save();
    }

    private function possuiFaixaRegistrada(): bool
    {
        return filled($this->last_performance)
            || filled($this->last_question_volume)
            || filled($this->last_accuracy_rate);
    }
}
