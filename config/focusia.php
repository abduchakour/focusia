<?php

/*
| FocusIA business settings. Every value can be overridden in .env.
*/

return [

    // Private disk for uploaded files ("local" in dev, "s3" for R2/B2 later).
    'files_disk' => env('FOCUSIA_FILES_DISK', 'local'),

    // Rule 20: maximum size per file, in kilobytes (10 MB by default).
    'max_upload_kb' => (int) env('FOCUSIA_MAX_UPLOAD_KB', 10240),

    // Rule 20: accepted file types. Each extension lists the MIME types its REAL content may
    // have (detected from the bytes, not from the name): a .exe renamed .pdf is refused, and
    // so is a .docx renamed .pdf. Old Office files are often detected as "vnd.ms-office" or
    // "CDFV2", new ones (docx, xlsx, pptx, odt are zip archives) sometimes as "zip".
    'file_types' => [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/CDFV2'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/CDFV2'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ],

    // Lifetime of a temporary download link, in minutes.
    'signed_url_minutes' => (int) env('FOCUSIA_SIGNED_URL_MINUTES', 10),

    // A student is "in difficulty" when the quiz average is below `average`
    // OR the success rate on at least one notion is below `notion_rate`.
    'difficulty' => [
        'average' => (float) env('FOCUSIA_DIFFICULTY_AVERAGE', 10),
        'notion_rate' => (float) env('FOCUSIA_DIFFICULTY_NOTION_RATE', 0.5),
    ],

    // Offline answers: tolerance added to the quiz duration (device clocks drift).
    'quiz_grace_seconds' => (int) env('FOCUSIA_QUIZ_GRACE_SECONDS', 60),

    // Login rate limiting: attempts per minute for one e-mail + IP.
    'login_max_attempts' => (int) env('FOCUSIA_LOGIN_MAX_ATTEMPTS', 5),

    // Group access codes: 6 characters, without ambiguous ones (0/O, 1/I/L).
    'access_code_alphabet' => 'ABCDEFGHJKMNPQRSTUVWXYZ23456789',
];
