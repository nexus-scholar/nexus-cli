<?php

namespace App\Console\Commands;

class NexusFetchFullText extends NexusFetchPdfs
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nexus:fetch-full-text
        {screen? : path to screen JSON, defaults to latest}
        {--destination= : storage-disk folder, defaults to full-text/{run_id}}
        {--max-attempts=2 : max download attempts per source}
        {--max-bytes=50000000 : max artifact size in bytes}
        {--cooldown=3600 : seconds before retrying a recently failed source URL}
        {--json : output a machine-readable retrieval summary}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retrieve legal open-access full text for included papers through nexus-scholar/core.';
}
