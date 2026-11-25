<?php

namespace App\Models;

use App\Enums\PointTransactionType;
use Database\Factories\PointTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'task_id', 'amount', 'type', 'note'])]
class PointTransaction extends Model
{
    /** @use HasFactory<PointTransactionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PointTransactionType::class,
            'amount' => 'integer',
        ];
    }

    /**
     * The user the transaction belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The task the transaction relates to, if any.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
