<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\PointsService;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

class SocialAuthController extends Controller
{
    /**
     * Redirect the visitor to the social provider's authorization page.
     */
    public function redirect(string $provider): RedirectResponse
    {
        $this->ensureProviderIsSupported($provider);

        $config = config("services.{$provider}", []);

        if (blank($config['client_id'] ?? null) || blank($config['client_secret'] ?? null)) {
            return $this->errorRedirect(ucfirst($provider).' sign-in is not configured yet.');
        }

        try {
            return Socialite::driver($provider)->stateless()->redirect();
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorRedirect('We could not start '.ucfirst($provider).' sign-in. Please try again.');
        }
    }

    /**
     * Handle the callback from the social provider and sign the user in.
     */
    public function callback(string $provider): RedirectResponse
    {
        $this->ensureProviderIsSupported($provider);

        try {
            $socialiteUser = Socialite::driver($provider)->stateless()->user();
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorRedirect('We could not sign you in with '.ucfirst($provider).'. Please try again.');
        }

        $email = $socialiteUser->getEmail();

        if (blank($email)) {
            return $this->errorRedirect(
                ucfirst($provider).' did not share an email address. Please use another sign-in method.'
            );
        }

        $user = $this->findOrCreateUser($provider, $socialiteUser, $email);

        $token = $user->createToken('auth-token')->plainTextToken;

        return redirect()->away(
            $this->frontendUrl('/auth/callback').'?'.http_build_query(['token' => $token])
        );
    }

    /**
     * Resolve the user for a social login, linking or creating accounts as needed.
     */
    private function findOrCreateUser(string $provider, SocialiteUser $socialiteUser, string $email): User
    {
        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', (string) $socialiteUser->getId())
            ->first();

        if ($account) {
            return $account->user;
        }

        $user = User::query()->firstWhere('email', $email);

        if (! $user) {
            $user = User::create([
                'name' => $socialiteUser->getName()
                    ?: $socialiteUser->getNickname()
                    ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Str::random(40),
                'avatar' => $socialiteUser->getAvatar(),
                'avatar_url' => $socialiteUser->getAvatar(),
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            app(PointsService::class)->grantBonus(
                $user,
                (int) config('points.signup_bonus'),
                'Welcome bonus',
            );
        } elseif (blank($user->getRawOriginal('avatar_url')) && filled($socialiteUser->getAvatar())) {
            $user->forceFill([
                'avatar' => $socialiteUser->getAvatar(),
                'avatar_url' => $socialiteUser->getAvatar(),
            ])->save();
        }

        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => (string) $socialiteUser->getId(),
        ]);

        return $user;
    }

    /**
     * Ensure the requested provider is enabled for this application.
     */
    private function ensureProviderIsSupported(string $provider): void
    {
        abort_unless(
            in_array($provider, config('taskmate.social_providers'), true),
            404,
        );
    }

    /**
     * Redirect back to the frontend with an authentication error.
     */
    private function errorRedirect(string $message): RedirectResponse
    {
        return redirect()->away(
            $this->frontendUrl('/auth/callback').'?'.http_build_query(['social_error' => $message])
        );
    }

    /**
     * Build an absolute frontend URL for the given path.
     */
    private function frontendUrl(string $path): string
    {
        return rtrim((string) config('taskmate.frontend_url'), '/').$path;
    }
}
