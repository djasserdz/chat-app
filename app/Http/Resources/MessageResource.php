<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'profile_picture' => $this->user->profile_picture ?? '/default-avatar.png',
            ],
            'attachments' => $this->attachments?->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'file_url' => Storage::url($attachment->file_path), // Adjusted for proper file access
                    'type' => $attachment->type, // Ensure 'type' exists in the Attachment model
                ];
            }),
        ];
    }
}
