<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracao extends Model
{
    protected $table = 'configuracoes';

    protected $fillable = [
        'chave',
        'valor',
    ];

    public static function valor(string $chave, ?string $default = null): ?string
    {
        $row = static::query()->where('chave', $chave)->first();
        if ($row === null) {
            return $default;
        }

        $valor = $row->valor;

        return $valor === null || $valor === '' ? $default : (string) $valor;
    }

    public static function definir(string $chave, string $valor): void
    {
        static::query()->updateOrCreate(
            ['chave' => $chave],
            ['valor' => $valor],
        );
    }
}
