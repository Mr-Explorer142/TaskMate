<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AvatarService;
use App\Services\CvService;
use Cloudinary\Cloudinary;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Cloudinary::class, function (): Cloudinary {
            $url = (string) config('services.cloudinary.url');

            if ($url === '') {
                throw new RuntimeException('Cloudinary is not configured.');
            }

            return new Cloudinary($url);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::deleting(function (User $user): void {
            if (filled($user->avatar_public_id)) {
                try {
                    app(AvatarService::class)->delete($user->avatar_public_id);
                } catch (Throwable) {
                    // Storage cleanup must not prevent the account from being deleted.
                }
            }

            if (filled($user->cv_path)) {
                try {
                    app(CvService::class)->delete($user->cv_path);
                } catch (Throwable) {
                    // Storage cleanup must not prevent the account from being deleted.
                }
            }
        });
    }
}
