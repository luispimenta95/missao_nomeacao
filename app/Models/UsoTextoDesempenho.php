<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsoTextoDesempenho extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'usos_texto_desempenho';

    protected $fillable = [
        'aluno_id',
        'faixa_desempenho_id',
        'texto_faixa_desempenho_id',
        'eixo_codigo',
        'ciclo',
        'periodo',
    ];

    protected function casts(): array
    {
        return [
            'ciclo' => 'integer',
        ];
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class, 'aluno_id');
    }

    public function faixa(): BelongsTo
    {
        return $this->belongsTo(FaixaDesempenho::class, 'faixa_desempenho_id');
    }

    public function texto(): BelongsTo
    {
        return $this->belongsTo(TextoFaixaDesempenho::class, 'texto_faixa_desempenho_id');
    }
}
