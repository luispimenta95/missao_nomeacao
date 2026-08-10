<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aluno extends Model
{
    use HasFactory;

    protected $table = 'alunos';

    protected $fillable = [
        'nome',
        'email',
        'recebe_email',
        'last_performance',
    ];

    protected $casts = [
        'recebe_email' => 'boolean',
    ];
}
