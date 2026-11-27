<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteMessageRequest;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
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
                'userOne:id,name,avatar,avatar_url',
                'userTwo:id,name,avatar,avatar_url',
                'lastMessage',
                'lastMessage.deletedByUsers' => fn ($query) => $query->where('user_id', $viewerId),
            ])
            ->withCount([
                'messages as unread_count' => fn (Builder $query) => $query
                    ->where('sender_id', '!=', $viewerId)
                    ->whereNull('read_at')
                    ->whereNull('deleted_for_everyone_at')
                    ->whereDoesntHave(
                        'deletedByUsers',
                        fn (Builder $query) => $query->where('user_id', $viewerId)
                    ),
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
            $conversation = DB::transaction(function () use ($task, $userOneId, $userTwoId): Conversation {
                $conversation = new Conversation;
                $conversation->forceFill([
                    'task_id' => $task->id,
                    'user_one_id' => $userOneId,
                    'user_two_id' => $userTwoId,
                ]);
                $conversation->save();

                return $conversation;
            });

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
            ->whereNull('deleted_for_everyone_at')
            ->where('sender_id', '!=', $viewerId)
            ->whereDoesntHave('deletedByUsers', fn (Builder $query) => $query->where('user_id', $viewerId))
            ->whereHas('conversation', function (Builder $query) use ($viewerId): void {
                $query->where('user_one_id', $viewerId)
                    ->orWhere('user_two_id', $viewerId);
            })
            ->count();

        return ApiResponse::success(['count' => $count], 'Unread message count.');
    }

    /**
     * List visible messages in a conversation.
     */
    public function messages(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        $viewerId = $request->user()->id;
        $this->ensureParticipant($conversation, $request->user());

        $data = $request->validate([
            'before_id' => ['nullable', 'integer', 'min:1'],
            'after_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = $conversation->messages()
            ->whereDoesntHave(
                'deletedByUsers',
                fn (Builder $query) => $query->where('user_id', $viewerId)
            )
            ->with([
                'deletedByUsers' => fn ($query) => $query->where('user_id', $viewerId),
                'replyTo.deletedByUsers' => fn ($query) => $query->where('user_id', $viewerId),
            ])
            ->getQuery();

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
        $viewer = $request->user();
        $this->ensureParticipant($conversation, $viewer);

        $replyToId = $request->validated('reply_to_id');

        if ($replyToId !== null) {
            $replyTo = Message::query()->findOrFail($replyToId);
            $this->ensureMessageInConversation($conversation, $replyTo);
            abort_if(
                $replyTo->isDeletedForEveryone() || $replyTo->isDeletedForUser($viewer->id),
                422,
                'You cannot reply to a deleted message.',
            );
        }

        $message = new Message;
        $message->forceFill([
            'conversation_id' => $conversation->id,
            'sender_id' => $viewer->id,
            'body' => $request->validated('body'),
            'reply_to_id' => $replyToId,
        ]);
        $message->save();

        return ApiResponse::success(
            new MessageResource($this->loadMessage($message, $viewer->id)),
            'Message sent.',
            201,
        );
    }

    /**
     * Edit a message the authenticated user sent.
     */
    public function updateMessage(
        UpdateMessageRequest $request,
        Conversation $conversation,
        Message $message,
    ): JsonResponse {
        $viewer = $request->user();
        $this->ensureParticipant($conversation, $viewer);
        $this->ensureMessageInConversation($conversation, $message);

        abort_unless($message->sender_id === $viewer->id, 403, 'Only the sender can edit a message.');
        abort_if(
            $message->isDeletedForEveryone() || $message->isDeletedForUser($viewer->id),
            409,
            'Deleted messages cannot be edited.',
        );

        $message->forceFill([
            'body' => $request->validated('body'),
            'edited_at' => now(),
        ])->save();

        return ApiResponse::success(
            new MessageResource($this->loadMessage($message, $viewer->id)),
            'Message updated.',
        );
    }

    /**
     * Delete a message for the viewer or for everyone in the conversation.
     */
    public function destroyMessage(
        DeleteMessageRequest $request,
        Conversation $conversation,
        Message $message,
    ): JsonResponse {
        $viewer = $request->user();
        $this->ensureParticipant($conversation, $viewer);
        $this->ensureMessageInConversation($conversation, $message);

        $scope = $request->validated('scope');

        if ($scope === 'everyone') {
            abort_unless($message->sender_id === $viewer->id, 403, 'Only the sender can delete a message for everyone.');

            if (! $message->isDeletedForEveryone()) {
                $message->forceFill(['deleted_for_everyone_at' => now()])->save();
            }
        } else {
            DB::table('message_deletions')->updateOrInsert(
                ['message_id' => $message->id, 'user_id' => $viewer->id],
                ['deleted_at' => now(), 'updated_at' => now()],
            );
        }

        return ApiResponse::success(
            new MessageResource($this->loadMessage($message->refresh(), $viewer->id)),
            $scope === 'everyone' ? 'Message deleted for everyone.' : 'Message deleted for you.',
        );
    }

    /**
     * Mark all visible messages from the other participant as read.
     */
    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $viewerId = $request->user()->id;
        $this->ensureParticipant($conversation, $request->user());

        $updated = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $viewerId)
            ->whereNull('read_at')
            ->whereNull('deleted_for_everyone_at')
            ->whereDoesntHave('deletedByUsers', fn (Builder $query) => $query->where('user_id', $viewerId))
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
     * Ensure the message is part of the current conversation.
     */
    private function ensureMessageInConversation(Conversation $conversation, Message $message): void
    {
        abort_unless($message->conversation_id === $conversation->id, 404, 'Message not found in this conversation.');
    }

    /**
     * Load the relations used to render one message for the given viewer.
     */
    private function loadMessage(Message $message, int $viewerId): Message
    {
        return $message->load([
            'deletedByUsers' => fn ($query) => $query->where('user_id', $viewerId),
            'replyTo.deletedByUsers' => fn ($query) => $query->where('user_id', $viewerId),
        ]);
    }

    /**
     * Eager load everything the conversation resource needs for one viewer.
     */
    private function loadConversation(Conversation $conversation, int $viewerId): Conversation
    {
        return $conversation
            ->load([
                'task:id,title',
                'userOne:id,name,avatar,avatar_url',
                'userTwo:id,name,avatar,avatar_url',
                'lastMessage',
                'lastMessage.deletedByUsers' => fn ($query) => $query->where('user_id', $viewerId),
            ])
            ->loadCount([
                'messages as unread_count' => fn (Builder $query) => $query
                    ->where('sender_id', '!=', $viewerId)
                    ->whereNull('read_at')
                    ->whereNull('deleted_for_everyone_at')
                    ->whereDoesntHave(
                        'deletedByUsers',
                        fn (Builder $query) => $query->where('user_id', $viewerId)
                    ),
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
