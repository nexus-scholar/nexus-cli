<?php

namespace App\Search;

final readonly class SearchSelection
{
    private function __construct(
        public bool $all,
        public ?string $id,
    ) {}

    public static function all(): self
    {
        return new self(true, null);
    }

    public static function only(string $id): self
    {
        $id = trim($id);
        if ($id === '') {
            throw new \InvalidArgumentException('Query ID must not be empty.');
        }

        return new self(false, $id);
    }

    public static function fromOptions(mixed $id, bool $all): self
    {
        $id = is_string($id) ? trim($id) : null;

        if (! $id && ! $all) {
            throw new \InvalidArgumentException('You must specify either --id=QUERY_ID or --all.');
        }

        if ($id && $all) {
            throw new \InvalidArgumentException('Use either --id=QUERY_ID or --all, not both.');
        }

        return $all ? self::all() : self::only((string) $id);
    }
}
