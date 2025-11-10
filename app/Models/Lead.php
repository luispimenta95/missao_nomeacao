<?php

namespace App\Models;

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
        'utm_source',
        'utm_medium',
        'utm_campaign',
    ];

    protected $casts = [
        'consent' => 'boolean',
    ];
}
