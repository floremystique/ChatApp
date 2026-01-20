<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchQuestion extends Model
{
    protected $fillable = ['key','category','ui_type','title','subtitle','is_active','order'];

    public function options()
    {
        return $this->hasMany(MatchQuestionOption::class);
    }
}
