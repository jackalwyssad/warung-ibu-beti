<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    protected $table = 'outbox_events';

    protected $fillable = [
        'message_id',
        'event_type',
        'routing_key',
        'payload',
        'status',
        'published_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'published_at' => 'datetime',
    ];
}
