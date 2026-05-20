<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Nexus Scholarly Provider Configurations
    |--------------------------------------------------------------------------
    |
    | API keys for external scholarly search providers.
    | Never hardcode these keys in version control; always use .env variables.
    |
    */

    'project_start_date' => env('NEXUS_PROJECT_START_DATE', '2026-05-01'),

    'mail_to' => env('NEXUS_MAIL_TO', 'admin@example.com'),

    'search' => [
        'queries_path' => env('NEXUS_QUERIES_PATH', 'queries/thesis-queries.yml'),
    ],

    'providers' => [
        'ieee' => [
            'api_key' => env('NEXUS_IEEE_API_KEY'),
        ],

        'semantic_scholar' => [
            'api_key' => env('NEXUS_S2_API_KEY'),
        ],

        'pubmed' => [
            'api_key' => env('NEXUS_PUBMED_API_KEY'),
        ],
    ],

    'dissemination' => [
        'pdf_storage_disk' => env('NEXUS_FULL_TEXT_DISK', 'public'),
    ],

    'full_text' => [
        'sources' => [
            'direct' => [
                'enabled' => env('NEXUS_FULL_TEXT_DIRECT_ENABLED', true),
            ],

            'unpaywall' => [
                'enabled' => env('NEXUS_UNPAYWALL_ENABLED', true),
                'email' => env('NEXUS_UNPAYWALL_EMAIL'),
                'rate_limit' => env('NEXUS_UNPAYWALL_RATE_LIMIT', 1.0),
                'timeout' => env('NEXUS_UNPAYWALL_TIMEOUT', 10),
                'max_retries' => env('NEXUS_UNPAYWALL_MAX_RETRIES', 2),
            ],

            'pmc' => [
                'enabled' => env('NEXUS_PMC_ENABLED', true),
                'rate_limit' => env('NEXUS_PMC_RATE_LIMIT', 3.0),
                'timeout' => env('NEXUS_PMC_TIMEOUT', 15),
                'max_retries' => env('NEXUS_PMC_MAX_RETRIES', 2),
                'prefer_xml' => env('NEXUS_PMC_PREFER_XML', true),
            ],

            'europe_pmc' => [
                'enabled' => env('NEXUS_EUROPE_PMC_ENABLED', true),
                'rate_limit' => env('NEXUS_EUROPE_PMC_RATE_LIMIT', 1.0),
                'timeout' => env('NEXUS_EUROPE_PMC_TIMEOUT', 15),
                'max_retries' => env('NEXUS_EUROPE_PMC_MAX_RETRIES', 2),
                'prefer_pdf' => env('NEXUS_EUROPE_PMC_PREFER_PDF', true),
                'prefer_xml' => env('NEXUS_EUROPE_PMC_PREFER_XML', true),
            ],

            'arxiv' => [
                'enabled' => env('NEXUS_FULL_TEXT_ARXIV_ENABLED', true),
            ],

            'openalex' => [
                'enabled' => env('NEXUS_FULL_TEXT_OPENALEX_ENABLED', true),
            ],

            'semantic_scholar' => [
                'enabled' => env('NEXUS_FULL_TEXT_S2_ENABLED', true),
            ],

            'shadow_libraries' => [
                'enabled' => false,
            ],
        ],
    ],
];
