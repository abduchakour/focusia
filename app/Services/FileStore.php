<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Resource;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Uploaded files: stored on the private disk (never under public/), downloaded only
 * through short-lived signed links bound to the user who asked for them.
 */
class FileStore
{
    /** Kinds of content whose file can be downloaded, and their model. */
    public const KINDS = [
        'support' => Material::class,
        'ressource' => Resource::class,
        'rendu' => Submission::class,
    ];

    public function disk(): FilesystemAdapter
    {
        return Storage::disk(config('focusia.files_disk'));
    }

    /**
     * Stores the file under a random name and returns the columns to save.
     *
     * @return array{file_path: string, file_name: string, mime_type: string, file_size: int}
     */
    public function store(UploadedFile $file, string $folder): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $this->disk()->putFileAs($folder, $file, Str::random(40).($extension ? ".$extension" : ''));

        return [
            'file_path' => $path,
            'file_name' => $this->cleanName($file->getClientOriginalName()),
            'mime_type' => (string) $file->getMimeType(), // detected from the content
            'file_size' => (int) $file->getSize(),
        ];
    }

    /** @return array{file_path: null, file_name: null, mime_type: null, file_size: null} */
    public function forget(?string $path): array
    {
        if ($path) {
            $this->disk()->delete($path);
        }

        return ['file_path' => null, 'file_name' => null, 'mime_type' => null, 'file_size' => null];
    }

    /** Temporary link (config focusia.signed_url_minutes), only valid for this user. */
    public function temporaryUrl(string $kind, Model $content, User $user): string
    {
        return URL::temporarySignedRoute(
            'files.download',
            now()->addMinutes((int) config('focusia.signed_url_minutes')),
            ['kind' => $kind, 'id' => $content->getKey(), 'user' => $user->id],
        );
    }

    /** Keeps the name readable but harmless in a Content-Disposition header. */
    private function cleanName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\"<>:|?*]+/u', '-', $name) ?? 'fichier';

        return Str::limit(trim($name, ' .-') ?: 'fichier', 180, '');
    }
}
