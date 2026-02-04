<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $fillable = [
        'chat_room_id',
        'user_id',
        'client_message_id',
        'body',
        'reply_to_id',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function isDeleted(): bool
    {
        return !is_null($this->deleted_at);
    }
}
