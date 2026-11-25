<?php

namespace App\Http\Controllers;

use App\Services\FileStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads. "link" checks the access rights and sends a temporary signed link
 * (10 min, bound to the user); "download" re-checks everything and streams the
 * private file under its original name.
 */
class FileController extends Controller
{
    public function link(Request $request, string $kind, int $id, FileStore $files): RedirectResponse
    {
        $content = $this->find($kind, $id);

        return redirect()->away($files->temporaryUrl($kind, $content, $request->user()));
    }

    public function download(Request $request, string $kind, int $id, FileStore $files): StreamedResponse
    {
        // The signed link only works for the person it was made for.
        abort_unless((int) $request->query('user') === $request->user()->id, 403, 'Ce lien de téléchargement ne vous est pas destiné.');

        // Checked again: access may have been removed since the link was made (rule 18).
        $content = $this->find($kind, $id);
        $path = $content->getAttribute('file_path');
        abort_if(! $path || ! $files->disk()->exists($path), 404, "Ce fichier n'est plus disponible.");

        return $files->disk()->download($path, $content->getAttribute('file_name'), [
            'Content-Type' => $content->getAttribute('mime_type') ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function find(string $kind, int $id): Model
    {
        abort_unless(isset(FileStore::KINDS[$kind]), 404);

        /** @var Model $content */
        $content = FileStore::KINDS[$kind]::findOrFail($id);
        Gate::authorize('view', $content);

        return $content;
    }
}
