<?php

namespace App\Models;

use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'consent',
        'material_id',
        'utm_source',
        'utm_medium',
        'utm_campaign',
    ];

    protected $casts = [
        'consent' => 'boolean',
    ];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}
