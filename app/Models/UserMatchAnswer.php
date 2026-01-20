<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMatchAnswer extends Model
{
    protected $fillable = ['user_id','match_question_id','match_question_option_id','answer_payload'];

    protected $casts = [
        'answer_payload' => 'array',
    ];

    public function option()
    {
        return $this->belongsTo(\App\Models\MatchQuestionOption::class, 'match_question_option_id');
    }

}
