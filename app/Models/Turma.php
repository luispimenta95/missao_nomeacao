<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Turma extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'logo_path',
        'checkout_url',
        'start_date',
        'available_slots',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
    ];

    public function inscricoes()
    {
        return $this->hasMany(Inscricao::class);
    }

    public function materials()
    {
        return $this->belongsToMany(Material::class);
    }
}
