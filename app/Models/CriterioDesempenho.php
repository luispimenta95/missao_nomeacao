<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CriterioDesempenho extends Model
{
    protected $table = 'criterios_desempenho';

    protected $fillable = [
        'codigo',
        'nome',
        'unidade',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
        ];
    }

    public function regras(): HasMany
    {
        return $this->hasMany(RegraDesempenho::class, 'criterio_desempenho_id');
    }
}
