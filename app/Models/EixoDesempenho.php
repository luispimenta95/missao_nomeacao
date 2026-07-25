<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EixoDesempenho extends Model
{
    protected $table = 'eixos_desempenho';

    public const CONSTANCIA = 'constancia';

    public const VOLUME_QUESTOES = 'volume_questoes';

    public const PERCENTUAL_ACERTOS = 'percentual_acertos';

    public const ASSUNTO = 'assunto';

    protected $fillable = [
        'codigo',
        'nome',
        'descricao',
        'ordem',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function faixas(): HasMany
    {
        return $this->hasMany(FaixaDesempenho::class, 'eixo_desempenho_id')->orderBy('ordem');
    }
}
