<?php

namespace App\Services;

use App\Messaging\RabbitPublisher;
use App\Models\OutboxEvent;
use Illuminate\Support\Facades\Log;

class OutboxPublisherService
{
    public function __construct(private RabbitPublisher $publisher) {}

    public function publishPending(int $limit = 50): int
    {
        $pendingEvents = OutboxEvent::where('status', 'pending')
            ->orderBy('id')
            ->take($limit)
            ->get();

        $count = 0;
        foreach ($pendingEvents as $outbox) {
            try {
                $this->publisher->publish(
                    $outbox->routing_key,
                    $outbox->payload,
                    $outbox->message_id,
                    $outbox->event_type
                );

                $outbox->update([
                    'status' => 'published',
                    'published_at' => now(),
                ]);
                $count++;
            } catch (\Throwable $e) {
                Log::error("Gagal mengirim outbox ID {$outbox->id} ke broker: " . $e->getMessage());
                break; // Stop jika broker offline
            }
        }

        return $count;
    }
}
