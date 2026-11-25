<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\PointsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Verification codes expire after this many minutes.
     */
    private const CODE_TTL_MINUTES = 15;

    /**
     * Register a new user, send a verification code and issue a personal access token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());
        app(PointsService::class)->grantBonus(
            $user,
            (int) config('points.signup_bonus'),
            'Welcome bonus',
        );
        $this->sendVerificationCode($user);
        $user->load('profileLinks');
        $user->loadAvg('reviewsReceived', 'rating');
        $user->loadCount('reviewsReceived');

        $token = $user->createToken('auth-token')->plainTextToken;

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Registration successful.', 201);
    }

    /**
     * Authenticate a user and issue a personal access token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $user->load('profileLinks');
        $user->loadAvg('reviewsReceived', 'rating');
        $user->loadCount('reviewsReceived');

        $token = $user->createToken('auth-token')->plainTextToken;

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Login successful.');
    }

    /**
     * Verify a user's email address using the emailed code.
     */
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        $isValid = $user
            && filled($user->email_verification_code)
            && $user->email_verification_expires_at?->isFuture()
            && Hash::check($data['code'], $user->email_verification_code);

        if (! $isValid) {
            throw ValidationException::withMessages([
                'code' => ['This verification code is invalid or has expired.'],
            ]);
        }

        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?? now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
        ])->save();

        $user->load('profileLinks');
        $user->loadAvg('reviewsReceived', 'rating');
        $user->loadCount('reviewsReceived');

        return ApiResponse::success(new UserResource($user), 'Email verified successfully.');
    }

    /**
     * Resend a fresh verification code to the authenticated user.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at !== null) {
            $user->load('profileLinks');
            $user->loadAvg('reviewsReceived', 'rating');
            $user->loadCount('reviewsReceived');

            return ApiResponse::success(new UserResource($user), 'Your email is already verified.');
        }

        $this->sendVerificationCode($user);

        return ApiResponse::success(null, 'Verification code sent.');
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return ApiResponse::success(null, 'Logged out.');
    }

    /**
     * Return the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('profileLinks');
        $user->loadAvg('reviewsReceived', 'rating');
        $user->loadCount('reviewsReceived');

        return ApiResponse::success(new UserResource($user), 'Authenticated user.');
    }

    /**
     * Generate a new code, store its hash and email it to the user.
     */
    private function sendVerificationCode(User $user): void
    {
        $code = $this->generateVerificationCode();

        $user->forceFill([
            'email_verification_code' => Hash::make($code),
            'email_verification_expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ])->save();

        $user->notify(new VerifyEmailNotification($code));
    }

    /**
     * Generate a 6-character code containing both letters and digits.
     */
    private function generateVerificationCode(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';

            for ($index = 0; $index < 6; $index++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (! preg_match('/[A-Z]/', $code) || ! preg_match('/\d/', $code));

        return $code;
    }
}
