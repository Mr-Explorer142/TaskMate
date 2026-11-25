<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConversationRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    /**
     * List the authenticated user's conversations.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $viewerId = $request->user()->id;

        $conversations = Conversation::query()
            ->where(function (Builder $query) use ($viewerId): void {
                $query->where('user_one_id', $viewerId)
                    ->orWhere('user_two_id', $viewerId);
            })
            ->with([
                'task:id,title',
                'userOne:id,name,avatar',
                'userTwo:id,name,avatar',
                'lastMessage',
            ])
            ->withCount([
                'messages as unread_count' => fn (Builder $query) => $query
                    ->where('sender_id', '!=', $viewerId)
                    ->whereNull('read_at'),
            ])
            ->withMax('messages', 'created_at')
            ->withMax('messages', 'id')
            ->orderByDesc('messages_max_created_at')
            ->orderByDesc('messages_max_id')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return ConversationResource::collection($conversations);
    }

    /**
     * Get or create a conversation between the authenticated user and another user.
     */
    public function store(StoreConversationRequest $request): JsonResponse
    {
        $viewer = $request->user();
        $task = Task::query()->findOrFail($request->validated('task_id'));
        $otherUser = User::query()->findOrFail($request->validated('user_id'));

        if ($viewer->id === $otherUser->id) {
            abort(422, 'You cannot message yourself.');
        }

        $this->ensureAllowedPair($viewer, $otherUser, $task);

        [$userOneId, $userTwoId] = $viewer->id < $otherUser->id
            ? [$viewer->id, $otherUser->id]
            : [$otherUser->id, $viewer->id];

        $created = false;

        try {
            $conversation = DB::transaction(fn (): Conversation => Conversation::query()->create([
                'task_id' => $task->id,
                'user_one_id' => $userOneId,
                'user_two_id' => $userTwoId,
            ]));

            $created = true;
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKey($exception)) {
                throw $exception;
            }

            $conversation = Conversation::query()
                ->where('task_id', $task->id)
                ->where('user_one_id', $userOneId)
                ->where('user_two_id', $userTwoId)
                ->firstOrFail();
        }

        $conversation = $this->loadConversation($conversation, $viewer->id);

        return ApiResponse::success(
            new ConversationResource($conversation),
            $created ? 'Conversation created.' : 'Conversation already exists.',
            $created ? 201 : 200,
        );
    }

    /**
     * Return the total number of unread messages for the authenticated user.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $viewerId = $request->user()->id;

        $count = Message::query()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $viewerId)
            ->whereHas('conversation', function (Builder $query) use ($viewerId): void {
                $query->where('user_one_id', $viewerId)
                    ->orWhere('user_two_id', $viewerId);
            })
            ->count();

        return ApiResponse::success(['count' => $count], 'Unread message count.');
    }

    /**
     * List messages in a conversation.
     */
    public function messages(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        $this->ensureParticipant($conversation, $request->user());

        $data = $request->validate([
            'before_id' => ['nullable', 'integer', 'min:1'],
            'after_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = $conversation->messages()->getQuery();

        if (isset($data['after_id'])) {
            $messages = $query
                ->where('id', '>', $data['after_id'])
                ->orderBy('id')
                ->limit(100)
                ->get();
        } elseif (isset($data['before_id'])) {
            $messages = $query
                ->where('id', '<', $data['before_id'])
                ->orderByDesc('id')
                ->limit(30)
                ->get()
                ->reverse()
                ->values();
        } else {
            $messages = $query
                ->orderByDesc('id')
                ->limit(30)
                ->get()
                ->reverse()
                ->values();
        }

        return MessageResource::collection($messages);
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->ensureParticipant($conversation, $request->user());

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return ApiResponse::success(new MessageResource($message), 'Message sent.', 201);
    }

    /**
     * Mark all messages from the other participant as read.
     */
    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $this->ensureParticipant($conversation, $request->user());

        $updated = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::success(['updated' => $updated], 'Messages marked as read.');
    }

    /**
     * Ensure the pair is allowed to discuss this task.
     */
    private function ensureAllowedPair(User $viewer, User $otherUser, Task $task): void
    {
        if ($viewer->id === $task->uploader_id) {
            $hasApplication = Application::query()
                ->where('task_id', $task->id)
                ->where('user_id', $otherUser->id)
                ->exists();

            abort_unless($hasApplication, 403, 'You can only message users who applied to this task.');

            return;
        }

        abort_unless(
            $otherUser->id === $task->uploader_id,
            403,
            'You can only message the task uploader.',
        );
    }

    /**
     * Ensure the authenticated user belongs to the conversation.
     */
    private function ensureParticipant(Conversation $conversation, User $user): void
    {
        abort_unless($conversation->isParticipant($user), 403, 'You are not part of this conversation.');
    }

    /**
     * Eager load everything the conversation resource needs for one viewer.
     */
    private function loadConversation(Conversation $conversation, int $viewerId): Conversation
    {
        return $conversation
            ->load([
                'task:id,title',
                'userOne:id,name,avatar',
                'userTwo:id,name,avatar',
                'lastMessage',
            ])
            ->loadCount([
                'messages as unread_count' => fn (Builder $query) => $query
                    ->where('sender_id', '!=', $viewerId)
                    ->whereNull('read_at'),
            ]);
    }

    /**
     * Determine whether the query exception is a duplicate key violation.
     */
    private function isDuplicateKey(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique constraint')
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique violation')
            || ($exception->errorInfo[1] ?? null) === 1062;
    }
}
