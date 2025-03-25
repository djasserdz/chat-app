<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;


    public $message;

    /**
     * Create a new event instance.
     *
     * @param  Message  $message
     * @return void
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel
     */
    public function broadcastOn()
    {
        // Broadcasting on the "chat" channel for a specific conversation
        return new Channel('chat.' . $this->message->conversation_id);
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'body' => $this->message->body,
                'user_id' => $this->message->user_id,
                'created_at' => $this->message->created_at->toIso8601String(),
                'attachments' => $this->message->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'type' => $attachment->type, // Assuming 'image', 'video', etc.
                        'file_url' => Storage::url($attachment->file_path), // Adjust as needed
                    ];
                }),
                'user' => $this->message->user ? [
                    'name' => $this->message->user->name
                ] : null
            ]
        ];
    }
}
