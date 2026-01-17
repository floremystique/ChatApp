<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatRoom extends Model
{
    protected $fillable = [
        'uuid','user_low','user_high','user_one','user_two',
        'user_one_typing_until','user_two_typing_until',
    ];

    public function userOne()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_one');
    }

    public function userTwo()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_two');
    }

    public function messages()
    {
        return $this->hasMany(\App\Models\Message::class, 'chat_room_id');
    }

    public function lastMessage()
    {
        return $this->hasOne(\App\Models\Message::class, 'chat_room_id')->latest('id');
    }

    protected static function booted()
    {
        static::creating(function ($room) {
            if (empty($room->uuid)) {
                $room->uuid = (string) Str::uuid();
            }
            if ($room->user_one && $room->user_two) {
                $room->user_low  = min($room->user_one, $room->user_two);
                $room->user_high = max($room->user_one, $room->user_two);
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
