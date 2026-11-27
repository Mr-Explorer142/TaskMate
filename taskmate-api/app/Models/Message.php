<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['body'])]
class Message extends Model
{
    /** The body returned when a message has been removed. */
    public const DELETED_BODY = 'This message was deleted.';

    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'edited_at' => 'datetime',
            'deleted_for_everyone_at' => 'datetime',
        ];
    }

    /**
     * The conversation the message belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * The user who sent the message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * The message this message replies to.
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /**
     * Messages that reply to this message.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'reply_to_id');
    }

    /**
     * Users who have hidden this message from their own view.
     */
    public function deletedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'message_deletions', 'message_id', 'user_id')
            ->withPivot('deleted_at');
    }

    /**
     * Determine whether the message was deleted for everyone.
     */
    public function isDeletedForEveryone(): bool
    {
        return $this->deleted_for_everyone_at !== null;
    }

    /**
     * Determine whether the given user hid the message from their own view.
     */
    public function isDeletedForUser(int $userId): bool
    {
        if ($this->relationLoaded('deletedByUsers')) {
            return $this->deletedByUsers->contains('id', $userId);
        }

        return $this->deletedByUsers()->where('user_id', $userId)->exists();
    }

    /**
     * Return the visible body for the given viewer.
     */
    public function bodyForViewer(?int $userId): string
    {
        if ($this->isDeletedForEveryone() || ($userId !== null && $this->isDeletedForUser($userId))) {
            return self::DELETED_BODY;
        }

        return $this->body;
    }
}
