<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContatoAluno extends Model
{
    protected $table = 'contatos_aluno';

    protected $fillable = [
        'aluno_id',
        'user_id',
        'observacao',
        'ocorrido_em',
    ];

    protected $casts = [
        'ocorrido_em' => 'datetime',
    ];

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
