<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

final class ApiResponse
{
    /**
     * Build the standard API response envelope.
     */
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        if ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        }

        return response()->json([
            'data' => $data,
            'message' => $message,
        ], $status);
    }

    /**
     * Build the standard API error envelope.
     *
     * @param  array<string, mixed>|null  $errors
     */
    public static function error(string $message, int $status, ?array $errors = null): JsonResponse
    {
        $payload = ['message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
