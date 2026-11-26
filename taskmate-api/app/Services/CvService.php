<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CvService
{
    /**
     * Store a CV privately on R2 using an opaque, user-scoped path.
     *
     * @return array{path: string, original_name: string}
     */
    public function store(UploadedFile $file, int $userId): array
    {
        $path = 'cvs/'.$userId.'/'.Str::uuid().'.pdf';

        try {
            $stored = Storage::disk('r2')->putFileAs(
                dirname($path),
                $file,
                basename($path),
                'private',
            );
        } catch (Throwable) {
            throw new RuntimeException('The CV could not be uploaded. Please try again.');
        }

        if ($stored === false || $stored === '') {
            throw new RuntimeException('The CV could not be uploaded. Please try again.');
        }

        return [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Delete a CV from R2.
     */
    public function delete(string $path): void
    {
        try {
            Storage::disk('r2')->delete($path);
        } catch (Throwable) {
            throw new RuntimeException('The CV could not be deleted. Please try again.');
        }
    }

    /**
     * Build a short-lived signed URL for a private CV.
     */
    public function temporaryUrl(string $path, int $minutes = 10): string
    {
        try {
            return Storage::disk('r2')->temporaryUrl($path, now()->addMinutes($minutes));
        } catch (Throwable) {
            throw new RuntimeException('A CV link could not be generated. Please try again.');
        }
    }
}
