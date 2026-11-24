<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uploader_id',
    'title',
    'description',
    'team_size',
    'type',
    'reward',
    'status',
    'deadline',
    'skills',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'status' => TaskStatus::class,
            'deadline' => 'date',
            'reward' => 'decimal:2',
            'skills' => 'array',
        ];
    }

    /**
     * The user who posted the task.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /**
     * All applications for the task.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * Applications that have been accepted.
     */
    public function acceptedApplications(): HasMany
    {
        return $this->hasMany(Application::class)->where('status', 'accepted');
    }

    /**
     * Reviews left for this task.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Number of accepted members on the task.
     */
    public function acceptedCount(): int
    {
        if ($this->relationLoaded('acceptedApplications')) {
            return $this->acceptedApplications->count();
        }

        if (array_key_exists('accepted_applications_count', $this->attributes)) {
            return (int) $this->attributes['accepted_applications_count'];
        }

        return $this->acceptedApplications()->count();
    }

    /**
     * Number of slots still available on the task.
     */
    public function openSlots(): int
    {
        return max(0, $this->team_size - $this->acceptedCount());
    }

    /**
     * Whether the task has no open slots left.
     */
    public function isFull(): bool
    {
        return $this->openSlots() === 0;
    }
}
