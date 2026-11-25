<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Resources\PointTransactionResource;
use App\Models\PointTransaction;
use App\Models\Task;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PointController extends Controller
{
    /**
     * Return the authenticated user's points balance and escrowed total.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $escrowed = (int) Task::query()
            ->where('uploader_id', $user->id)
            ->where('status', TaskStatus::InProgress->value)
            ->sum('escrow_amount');

        return ApiResponse::success([
            'balance' => (int) $user->points_balance,
            'escrowed' => $escrowed,
        ], 'Points balance.');
    }

    /**
     * List the authenticated user's point transactions.
     */
    public function transactions(Request $request): AnonymousResourceCollection
    {
        $transactions = PointTransaction::query()
            ->where('user_id', $request->user()->id)
            ->with('task:id,title')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return PointTransactionResource::collection($transactions);
    }
}
