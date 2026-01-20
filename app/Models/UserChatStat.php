<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserChatStat extends Model
{
    protected $fillable = [
        'user_id','reply_time_ema_sec','replies_count','reply_within_1h','reply_within_24h'
    ];
}
