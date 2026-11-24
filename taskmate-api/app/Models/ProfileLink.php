<?php

namespace App\Models;

use App\Enums\LinkPlatform;
use Database\Factories\ProfileLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'platform', 'label', 'url', 'sort_order'])]
class ProfileLink extends Model
{
    /** @use HasFactory<ProfileLinkFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (ProfileLink $link): void {
            if (blank($link->platform)) {
                $link->platform = LinkPlatform::detect((string) $link->url);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => LinkPlatform::class,
        ];
    }

    /**
     * The user that owns the link.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
