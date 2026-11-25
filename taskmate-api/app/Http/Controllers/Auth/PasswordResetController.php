<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    /**
     * Email a password reset link to the given address.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink(
            $request->only('email'),
            fn (User $user, string $token): mixed => $user->notify(new ResetPasswordNotification($token)),
        );

        return ApiResponse::success(null, 'If the account exists, a reset link has been sent.');
    }

    /**
     * Reset a password using a valid reset token.
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return ApiResponse::success(null, 'Password reset successfully.');
    }
}
