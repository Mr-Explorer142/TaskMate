<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'country',
    'bio',
    'skills',
    'avatar',
    'cv_path',
    'cv_original_name',
    'cv_uploaded_at',
    'points_balance',
])]
#[Hidden(['password', 'remember_token', 'cv_path'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_expires_at' => 'datetime',
            'password' => 'hashed',
            'skills' => 'array',
            'cv_uploaded_at' => 'datetime',
        ];
    }

    /**
     * Tasks posted by this user.
     */
    public function postedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'uploader_id');
    }

    /**
     * Applications submitted by this user.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * Social accounts linked to this user.
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Links shown on this user's profile.
     */
    public function profileLinks(): HasMany
    {
        return $this->hasMany(ProfileLink::class)->orderBy('sort_order');
    }

    /**
     * Reviews written by this user.
     */
    public function reviewsGiven(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    /**
     * Reviews received by this user.
     */
    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewee_id');
    }

    /**
     * Point transactions belonging to this user.
     */
    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    /**
     * The user's average rating, or null when they have no reviews.
     */
    protected function averageRating(): Attribute
    {
        return Attribute::make(
            get: function (): ?float {
                $average = array_key_exists('reviews_received_avg_rating', $this->attributes)
                    ? $this->attributes['reviews_received_avg_rating']
                    : $this->reviewsReceived()->avg('rating');

                return $average === null ? null : round((float) $average, 2);
            },
        );
    }

    /**
     * Whether the user has uploaded a CV.
     */
    protected function hasCv(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => filled($this->cv_path),
        );
    }
}
