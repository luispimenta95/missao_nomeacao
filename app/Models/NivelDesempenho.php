<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NivelDesempenho extends Model
{
    protected $table = 'niveis_desempenho';

    protected $fillable = [
        'nome',
        'slug',
        'ordem',
        'texto_email',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function regras(): HasMany
    {
        return $this->hasMany(RegraDesempenho::class, 'nivel_desempenho_id');
    }
}
