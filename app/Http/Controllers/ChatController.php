<?php

namespace App\Http\Controllers;

use App\Events\MessageSend;
use App\Events\MessageSent;
use App\Http\Resources\MessageResource;
use App\Http\Resources\UserResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

class ChatController extends Controller
{
    public function get_conversation(Request $request)
    {
        $auth_id = Auth::id();

        $conversations = Conversation::with([
            'users' => function ($query) use ($auth_id) {
                $query->where('users.id', '!=', $auth_id);
            },
            'messages' => function ($query) {
                $query->latest()->limit(1);
            }
        ])
            ->whereHas('users', function ($query) use ($auth_id) {
                $query->where('users.id', $auth_id);
            })
            ->get()
            ->map(function ($conversation) {
                $otherUser = $conversation->users->first();

                return [
                    'id' => $conversation->id,
                    'name' => $conversation->name,
                    'type' => $conversation->type,
                    'other_user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'profile_picture' => $otherUser->profile_picture
                            ? URL::to('/storage/' . $otherUser->profile_picture)
                            : '/default-avatar.png',
                    ],
                    'last_message' => $conversation->messages->first()
                        ? [
                            'content' => $conversation->messages->first()->content,
                            'created_at' => $conversation->messages->first()->created_at
                        ]
                        : null,
                ];
            });


        if ($request->expectsJson()) {
            return response()->json([
                'conversations' => $conversations
            ]);
        }


        return Inertia::render('Conversations', [
            'conversations' => $conversations,
            'users' => User::where('id', '!=', $auth_id)->get(),
        ]);
    }


    public function search_user(Request $request)
    {
        // Validate the input
        $request->validate([
            'search' => ['required', 'string', 'max:255'],
        ]);


        $search = $request->input('search');

        try {

            $users = User::where('name', 'LIKE', "%$search%")
                ->select('id', 'name', 'email', 'profile_picture')
                ->limit(10)
                ->get();


            return response()->json([
                'users' => UserResource::collection($users),
                'total' => $users->count()
            ]);
        } catch (\Exception $e) {

            Log::error('User search error: ' . $e->getMessage());


            return response()->json([
                'message' => 'An error occurred while searching users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function create_conversation(Request $request)
    {
        // Validate input with more robust rules
        $validatedData = $request->validate([
            'user_id' => ['required', 'exists:users,id', 'not_in:' . Auth::id()],
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:private,group'],
        ]);

        $auth_id = Auth::id();

        try {

            $targetUser = User::findOrFail($request->user_id);


            $existingConversation = Conversation::where('type', 'private')
                ->whereHas('users', function ($query) use ($auth_id, $request) {
                    $query->where('users.id', $auth_id)
                        ->orWhere('users.id', $request->user_id);
                }, '=', 2)
                ->first();


            if ($existingConversation) {
                return redirect("/chat/{$existingConversation->id}")
                    ->with('info', 'Conversation already exists');
            }


            $conversation = Conversation::create([
                'name' => $request->name ?? $targetUser->name,
                'type' => 'private',
            ]);


            $conversation->users()->attach([
                $auth_id => [
                    'role' => 'admin',
                    'joined_at' => now()
                ],
                $request->user_id => [
                    'role' => 'member',
                    'joined_at' => now()
                ]
            ]);


            return redirect("/chat/{$conversation->id}")
                ->with('success', 'Conversation created successfully');
        } catch (\Exception $e) {

            Log::error('Conversation creation error: ' . $e->getMessage());


            return redirect()->back()
                ->with('error', 'Failed to create conversation')
                ->withErrors($e->getMessage());
        }
    }
    public function getMessages(Request $request, $conversation_id)
    {
        $user_id = Auth::id();
        $conversation = Conversation::with('users')->whereHas('users', function ($query) use ($user_id) {
            $query->where('users.id', $user_id);
        })->findOrFail($conversation_id);

        $messages = Message::where('conversation_id', $conversation_id)->orderBy('created_at', 'ASC')->get();

        return Inertia::render('ChatMessages', [
            'conversation' => $conversation,
            'messages' => MessageResource::collection($messages)->toArray($request),
        ]);
    }
    public function sendMessage(Request $request, $conversation_id)
    {
        $request->validate([
            'content' => 'nullable|string',
            'file' => 'nullable|file|max:20480|mimes:jpg,jpeg,png,gif,mp4,mov,avi,mp3,wav,pdf,doc,docx,xls,xlsx',
        ]);


        $user_id = Auth::id();


        $isParticipant = Conversation::where('id', $conversation_id)
            ->whereHas('users', fn($query) => $query->where('users.id', $user_id))
            ->exists();

        if (!$isParticipant) {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }

        try {
            DB::beginTransaction();

            $filePath = null;
            $fileType = 'text';


            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $extension = $file->getClientOriginalExtension();


                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $fileType = 'image';
                } elseif (in_array($extension, ['mp4', 'mov', 'avi'])) {
                    $fileType = 'video';
                } elseif (in_array($extension, ['mp3', 'wav'])) {
                    $fileType = 'audio';
                } elseif (in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx'])) {
                    $fileType = 'document';
                }


                $filePath = $file->store("messages/{$fileType}s", 'public');
            }
            // Store the message
            $message = Message::create([
                'user_id' => $user_id,
                'conversation_id' => $conversation_id,
                'body' => $request->input('content') ?? null,
                'type' => $fileType,
            ]);

            if ($filePath) {
                $message->attachments()->create([
                    'file_path' => $filePath,
                    'file_type' => $fileType,
                ]);
            }


            event(new MessageSent($message));

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to send message.'], 500);
        }
    }


    public function create_group_conversation(Request $request)
    {
        // Validate group creation request
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'user_ids' => ['required', 'array', 'min:2'],
            'user_ids.*' => ['exists:users,id', 'distinct']
        ]);

        $auth_id = Auth::id();

        try {
            // Ensure all selected users exist and are not duplicates
            $userIds = array_unique(array_merge([$auth_id], $request->user_ids));

            // Validate that all users exist
            $existingUsers = User::whereIn('id', $userIds)->pluck('id')->toArray();

            if (count($existingUsers) !== count($userIds)) {
                throw new \Exception('One or more selected users are invalid');
            }

            // Check for existing similar group conversation
            $existingGroupConversation = Conversation::where('type', 'group')
                ->whereHas('users', function ($query) use ($userIds) {
                    $query->whereIn('users.id', $userIds);
                }, '=', count($userIds))
                ->first();

            if ($existingGroupConversation) {
                return redirect("/chat/{$existingGroupConversation->id}")
                    ->with('info', 'Similar group conversation already exists');
            }

            // Create new group conversation
            $conversation = Conversation::create([
                'name' => $request->name,
                'type' => 'group',
            ]);

            // Prepare user attachment data
            $userAttachData = [];
            foreach ($userIds as $userId) {
                $userAttachData[$userId] = [
                    'role' => $userId === $auth_id ? 'admin' : 'member',
                    'joined_at' => now()
                ];
            }

            // Attach users to the conversation
            $conversation->users()->attach($userAttachData);

            // Redirect to the new group conversation
            return redirect("/chat/{$conversation->id}")
                ->with('success', 'Group conversation created successfully');
        } catch (\Exception $e) {
            // Log the error
            Log::error('Group conversation creation error: ' . $e->getMessage());

            // Return with error
            return redirect()->back()
                ->with('error', 'Failed to create group conversation')
                ->withErrors($e->getMessage());
        }
    }
}
