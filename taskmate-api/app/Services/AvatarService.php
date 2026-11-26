<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

class AvatarService
{
    public function __construct(private readonly Cloudinary $cloudinary) {}

    /**
     * Upload an avatar to Cloudinary as a face-focused square thumbnail.
     *
     * @return array{public_id: string, url: string}
     */
    public function upload(UploadedFile $file): array
    {
        try {
            $result = $this->cloudinary->uploadApi()->upload(
                $file->getRealPath() ?: $file->getPathname(),
                [
                    'folder' => 'taskmate/avatars',
                    'resource_type' => 'image',
                    'transformation' => [
                        'width' => 400,
                        'height' => 400,
                        'crop' => 'thumb',
                        'gravity' => 'face',
                    ],
                ]
            );
        } catch (Throwable) {
            throw new RuntimeException('The avatar could not be uploaded. Please try again.');
        }

        return [
            'public_id' => (string) $result['public_id'],
            'url' => (string) $result['secure_url'],
        ];
    }

    /**
     * Permanently delete a Cloudinary avatar and invalidate its cached copies.
     */
    public function delete(string $publicId): void
    {
        try {
            $this->cloudinary->uploadApi()->destroy($publicId, ['invalidate' => true]);
        } catch (Throwable) {
            throw new RuntimeException('The avatar could not be deleted. Please try again.');
        }
    }
}
