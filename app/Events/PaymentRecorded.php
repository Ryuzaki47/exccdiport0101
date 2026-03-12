<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentRecorded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public int $transactionId,
        public float $amount,
        public string $reference,
        public ?int $triggeredBy = null,  // ← carry admin ID safely
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->user->id}");
    }

    public function broadcastAs(): string
    {
        return 'payment.recorded';
    }

    public function broadcastWith(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'amount'         => $this->amount,
            'reference'      => $this->reference,
            'timestamp'      => now(),
        ];
    }
}