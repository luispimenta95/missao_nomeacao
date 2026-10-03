<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TextoFaixaDesempenho extends Model
{
    protected $table = 'textos_faixa_desempenho';

    protected $fillable = [
        'faixa_desempenho_id',
        'texto',
        'ordem',
        'canonico',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'canonico' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function faixa(): BelongsTo
    {
        return $this->belongsTo(FaixaDesempenho::class, 'faixa_desempenho_id');
    }

    public function usos(): HasMany
    {
        return $this->hasMany(UsoTextoDesempenho::class, 'texto_faixa_desempenho_id');
    }
}
