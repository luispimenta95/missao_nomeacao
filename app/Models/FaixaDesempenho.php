<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaixaDesempenho extends Model
{
    protected $table = 'faixas_desempenho';

    protected $fillable = [
        'eixo_desempenho_id',
        'codigo',
        'nome',
        'valor_min',
        'valor_max',
        'ordem',
        'texto_email',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'valor_min' => 'float',
            'valor_max' => 'float',
            'ordem' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function eixo(): BelongsTo
    {
        return $this->belongsTo(EixoDesempenho::class, 'eixo_desempenho_id');
    }

    public function contemValor(float $valor): bool
    {
        $min = $this->valor_min;
        $max = $this->valor_max;

        if ($min !== null && $valor < $min) {
            return false;
        }
        if ($max !== null && $valor > $max) {
            return false;
        }

        return $min !== null || $max !== null;
    }
}
