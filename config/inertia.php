<?php

// Only the settings that differ from the package defaults (merged with them).
return [

    'testing' => [

        // Tests check that each rendered page exists. Our folder is "pages" (lowercase):
        // Windows ignores the case, Linux (GitHub Actions, servers) does not.
        'ensure_pages_exist' => true,

        'page_paths' => [
            resource_path('js/pages'),
        ],

        'page_extensions' => ['vue', 'ts', 'js'],

    ],

];
