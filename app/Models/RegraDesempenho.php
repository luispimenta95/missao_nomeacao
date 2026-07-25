<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegraDesempenho extends Model
{
    protected $table = 'regras_desempenho';

    public const OPERADORES = ['>=', '>', '<=', '<', '=', 'between'];

    protected $fillable = [
        'nivel_desempenho_id',
        'criterio_desempenho_id',
        'operador',
        'valor_min',
        'valor_max',
    ];

    protected function casts(): array
    {
        return [
            'valor_min' => 'float',
            'valor_max' => 'float',
        ];
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(NivelDesempenho::class, 'nivel_desempenho_id');
    }

    public function criterio(): BelongsTo
    {
        return $this->belongsTo(CriterioDesempenho::class, 'criterio_desempenho_id');
    }
}
