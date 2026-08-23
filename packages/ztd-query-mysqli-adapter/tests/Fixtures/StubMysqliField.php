<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class StubMysqliField
{
    public function __construct(
        public string $name,
        public int $type,
        public int|string $charsetnr,
    ) {
    }
}
