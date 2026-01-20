<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchQuestionOption extends Model
{
    protected $fillable = ['match_question_id','label','value','order','feature_deltas'];

    protected $casts = [
        'feature_deltas' => 'array',
    ];

    public function question()
    {
        return $this->belongsTo(MatchQuestion::class, 'match_question_id');
    }
}
