<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnonymousVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_token',
        'session_id',
        'ip_address',
        'ip_data',
        'user_agent',
        'referrer',
        'landing_page',
        'exit_page',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'entered_at',
        'last_seen_at',
        'exited_at',
        'duration_seconds',
    ];

    protected $casts = [
        'ip_data' => 'array',
        'entered_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'exited_at' => 'datetime',
    ];
}
