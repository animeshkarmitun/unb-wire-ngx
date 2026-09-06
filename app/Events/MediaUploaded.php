<?php

namespace App\Events;

use App\Models\MediaAsset;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MediaUploaded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MediaAsset $asset) {}

    public function broadcastOn(): array
    {
        return [new Channel('photo-desk')];
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->asset->id, 'title' => $this->asset->title, 'status' => $this->asset->status];
    }
}
