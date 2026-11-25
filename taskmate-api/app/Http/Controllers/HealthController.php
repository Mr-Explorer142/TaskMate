<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Report that the API process is serving requests.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
}
