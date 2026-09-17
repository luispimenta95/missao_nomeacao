<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RelatorioPdfPeriodo extends Model
{
    protected $table = 'relatorio_pdf_periodos';

    protected $fillable = [
        'year_month',
        'period',
        'label',
        'unlocked_at',
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
    ];
}
