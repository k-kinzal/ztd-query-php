<?php

declare(strict_types=1);

namespace Requirements\Source;

use Requirements\Model\Source;

final class Unit
{
    public function __construct(public readonly string $location, public readonly string $text)
    {
    }

    public function key(Source $source): string
    {
        return hash('sha256', $source->uri . "\0" . $source->format . "\0" . $this->location);
    }

    public static function normalize(string $text): string
    {
        return trim(preg_replace('/[\s\x{00a0}]+/u', ' ', $text) ?? $text);
    }
}
