<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMatchFeature extends Model
{
    protected $fillable = [
        'user_id',
        'stability_score','values_score','trust_score','conflict_risk',
        'intent','marriage_intent','marriage_timeline','kids_pref',
        'faith_importance','faith_must_match',
        'style_label','astrology_on','computed_at',
    ];

    protected $casts = [
        'faith_must_match' => 'boolean',
        'astrology_on' => 'boolean',
        'computed_at' => 'datetime',
    ];
}
