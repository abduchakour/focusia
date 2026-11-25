<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Rule 20: the extension must be accepted AND the real content (MIME type detected
 * from the bytes) must match that extension (config focusia.file_types).
 */
class AcceptedFile implements ValidationRule
{
    public const MESSAGE = "Ce type de fichier n'est pas accepté. Formats acceptés : PDF, Word, Excel, PowerPoint, ODT, JPG, PNG ou ZIP.";

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $types = config('focusia.file_types');
        $extension = strtolower($value->getClientOriginalExtension());
        $mime = strtolower((string) $value->getMimeType());

        if (! isset($types[$extension]) || ! in_array($mime, array_map('strtolower', $types[$extension]), true)) {
            $fail(self::MESSAGE);
        }
    }
}
