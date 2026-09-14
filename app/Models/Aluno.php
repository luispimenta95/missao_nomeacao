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
}
