<?php

namespace App\Http\Controllers;

use App\Events\MessageSend;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
                    'other_user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'profile_picture' => $otherUser->profile_picture
                            ? URL::to('/storage/' . $otherUser->profile_picture)
                            : '/default-avatar.png',
                    ],
                    'last_message' => $conversation->messages->first(),
                ];
            });
        return Inertia::render('Chats', [
            'conversations' => $conversations,
            'users' => User::where('id', '!=', $auth_id)->get(),
        ]);
    }


    public function search_user(Request $request)
    {
        $request->validate([
            'search' => ['required', 'string'],
        ]);
        $search = $request->input('search');
        $users = User::where('name', 'LIKE', "%$search%")->select('id', 'name')->get();

        return response()->json(['users' => $users]);
    }

    public function create_conversation(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string'],
            'type' => ['required', 'in:private,group'],
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $auth_id = Auth::id();

        $existingconversation = null;

        if ($request->type === 'private') {
            $existingconversation = Conversation::where('type', 'private')->whereHas('users', function ($query) use ($auth_id) {
                $query->where('users.id', $auth_id);
            })->whereHas('users', function ($query) use ($request) {
                $query->where('users.id', $request->user_id);
            })->first();
        }

        if ($existingconversation) {
            return redirect()->back()->with('error', 'Conversation already exists');
        }

        $conversations = Conversation::create([
            'name' => $request->name,
            'type' => $request->type,
        ]);

        $conversations->users()->attach($auth_id, ['role' => 'admin', 'joined_at' => now()]);
        $conversations->users()->attach($request->user_id, ['joined_at' => now()]);

        return back();
    }
    public function getMessages(Request $request, $conversation_id)
    {
        $user_id = Auth::id();
        $conversation = Conversation::with('users')->whereHas('users', function ($query) use ($user_id) {
            $query->where('users.id', $user_id);
        })->findOrFail($conversation_id);

        $messages = Message::where('conversation_id', $conversation_id)->with('user')->orderBy('created_at', 'ASC')->get();

        return Inertia::render('ChatMessages', [
            'conversation' => $conversation,
            'messages' => $messages,
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


            event(new MessageSent($message));

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to send message.'], 500);
        }
    }
}
