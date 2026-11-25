<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    /**
     * Force API requests to be treated as JSON requests and normalize errors.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $response = $next($request);

        if (! $response instanceof JsonResponse || $response->getStatusCode() < 400) {
            return $response;
        }

        $data = $response->getData(true);

        if (! is_array($data)) {
            return $response;
        }

        $message = is_string($data['message'] ?? null) ? $data['message'] : 'Request failed.';
        $errors = is_array($data['errors'] ?? null) ? $data['errors'] : null;

        return ApiResponse::error($message, $response->getStatusCode(), $errors);
    }
}
