<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aluno extends Model
{
    use HasFactory;

    protected $table = 'alunos';

    protected $fillable = [
        'tutory_id',
        'nome',
        'email',
        'recebe_email',
        'last_performance',
        'last_volume_questoes',
        'last_percentual_acertos',
        'last_assuntos',
    ];

    protected $casts = [
        'recebe_email' => 'boolean',
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
     * Grava as faixas do último relatório (constância, volume, % acertos, assuntos).
     *
     * @param  array{blocos?: list<array<string, mixed>>, metricas?: array<string, mixed>, resumo?: string|null}  $avaliacao
     */
    public function aplicarAvaliacaoDesempenho(array $avaliacao): void
    {
        $porEixo = [];
        foreach ($avaliacao['blocos'] ?? [] as $bloco) {
            if (! is_array($bloco)) {
                continue;
            }
            $eixo = (string) ($bloco['eixo'] ?? '');
            $nome = trim((string) ($bloco['faixa_nome'] ?? ''));
            if ($eixo === '' || $nome === '') {
                continue;
            }
            $porEixo[$eixo][] = $nome;
        }

        $metricas = is_array($avaliacao['metricas'] ?? null) ? $avaliacao['metricas'] : [];

        if (isset($porEixo[EixoDesempenho::CONSTANCIA][0])) {
            $this->last_performance = $porEixo[EixoDesempenho::CONSTANCIA][0];
        }

        if (isset($porEixo[EixoDesempenho::VOLUME_QUESTOES][0])) {
            $this->last_volume_questoes = $porEixo[EixoDesempenho::VOLUME_QUESTOES][0];
        }

        if (isset($porEixo[EixoDesempenho::PERCENTUAL_ACERTOS][0])) {
            $this->last_percentual_acertos = $porEixo[EixoDesempenho::PERCENTUAL_ACERTOS][0];
        } else {
            $totalQuestoes = $metricas['total_questoes'] ?? null;
            if (is_numeric($totalQuestoes) && (float) $totalQuestoes < 100) {
                $this->last_percentual_acertos = null;
            }
        }

        if (isset($porEixo[EixoDesempenho::ASSUNTO])) {
            $this->last_assuntos = implode(' · ', array_values(array_unique($porEixo[EixoDesempenho::ASSUNTO])));
        } elseif ((int) ($metricas['assuntos_avaliados'] ?? 0) > 0) {
            $this->last_assuntos = 'Sem pontos de atenção';
        }

        $this->save();
    }
}
