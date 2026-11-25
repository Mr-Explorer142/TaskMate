<?php

namespace App\Services;

use App\Enums\PointTransactionType;
use App\Models\PointTransaction;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PointsService
{
    /**
     * Grant bonus points to a user and record the matching ledger row.
     */
    public function grantBonus(User $user, int $amount, string $note): PointTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Bonus amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $note): PointTransaction {
            $locked = $this->lockUser($user);
            $locked->forceFill(['points_balance' => $locked->points_balance + $amount])->save();

            $transaction = PointTransaction::query()->create([
                'user_id' => $locked->id,
                'task_id' => null,
                'amount' => $amount,
                'type' => PointTransactionType::Bonus,
                'note' => $note,
            ]);

            $this->syncUserBalance($user, $locked);

            return $transaction;
        });
    }

    /**
     * Move points from the uploader into escrow for a task.
     */
    public function escrow(User $uploader, Task $task, int $amount): PointTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Escrow amount must be positive.');
        }

        return DB::transaction(function () use ($uploader, $task, $amount): PointTransaction {
            $lockedTask = Task::query()
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked = $this->lockUser($uploader);

            if ($locked->points_balance < $amount) {
                throw ValidationException::withMessages([
                    'points' => "Insufficient points: you need {$amount} and have {$locked->points_balance}",
                ]);
            }

            $locked->forceFill(['points_balance' => $locked->points_balance - $amount])->save();

            $transaction = PointTransaction::query()->create([
                'user_id' => $locked->id,
                'task_id' => $lockedTask->id,
                'amount' => -$amount,
                'type' => PointTransactionType::Escrow,
                'note' => "Escrow for {$lockedTask->title}",
            ]);

            $lockedTask->update(['escrow_amount' => $amount]);

            $this->syncUserBalance($uploader, $locked);
            $this->syncTaskEscrow($task, $lockedTask);

            return $transaction;
        });
    }

    /**
     * Release escrowed points to an accepted task member.
     */
    public function release(Task $task, User $member, int $amount): PointTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Release amount must be positive.');
        }

        return DB::transaction(function () use ($task, $member, $amount): PointTransaction {
            $lockedTask = Task::query()
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTask->escrow_amount < $amount) {
                throw ValidationException::withMessages([
                    'points' => 'Cannot release more points than are held in escrow.',
                ]);
            }

            $locked = $this->lockUser($member);
            $locked->forceFill(['points_balance' => $locked->points_balance + $amount])->save();

            $transaction = PointTransaction::query()->create([
                'user_id' => $locked->id,
                'task_id' => $lockedTask->id,
                'amount' => $amount,
                'type' => PointTransactionType::Release,
                'note' => "Released points for {$lockedTask->title}",
            ]);

            $lockedTask->update(['escrow_amount' => $lockedTask->escrow_amount - $amount]);

            $this->syncUserBalance($member, $locked);
            $this->syncTaskEscrow($task, $lockedTask);

            return $transaction;
        });
    }

    /**
     * Refund any remaining escrowed points to the task uploader.
     */
    public function refund(Task $task): ?PointTransaction
    {
        return DB::transaction(function () use ($task): ?PointTransaction {
            $lockedTask = Task::query()
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = (int) $lockedTask->escrow_amount;

            if ($amount <= 0) {
                $lockedTask->update(['escrow_amount' => 0]);
                $this->syncTaskEscrow($task, $lockedTask);

                return null;
            }

            $locked = $this->lockUser($lockedTask->uploader);

            $locked->forceFill(['points_balance' => $locked->points_balance + $amount])->save();

            $transaction = PointTransaction::query()->create([
                'user_id' => $locked->id,
                'task_id' => $lockedTask->id,
                'amount' => $amount,
                'type' => PointTransactionType::Refund,
                'note' => "Refunded escrow for {$lockedTask->title}",
            ]);

            $lockedTask->update(['escrow_amount' => 0]);

            $this->syncUserBalance($lockedTask->uploader, $locked);
            $this->syncTaskEscrow($task, $lockedTask);

            return $transaction;
        });
    }

    /**
     * Lock a user row for the duration of a points operation.
     */
    private function lockUser(User $user): User
    {
        return User::query()
            ->whereKey($user->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Copy the freshly locked balance back onto the caller's model instance.
     */
    private function syncUserBalance(User $original, User $locked): void
    {
        $original->setAttribute('points_balance', $locked->points_balance);
        $original->syncOriginalAttribute('points_balance');
    }

    /**
     * Copy the freshly locked escrow amount back onto the caller's model instance.
     */
    private function syncTaskEscrow(Task $original, Task $locked): void
    {
        $original->setAttribute('escrow_amount', $locked->escrow_amount);
        $original->syncOriginalAttribute('escrow_amount');
    }
}
