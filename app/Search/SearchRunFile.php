<?php

namespace App\Search;

final readonly class SearchRunFile
{
    public function __construct(
        public string $absolute,
        public string $relative,
    ) {}
}
