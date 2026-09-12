<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * Supplies native field metadata without a database result.
 */
final class StubMysqliField
{
    /**
     * Describe one native result field for metadata extraction tests.
     */
    public function __construct(
        public string $name,
        public int $type,
        public int|string $charsetnr,
    ) {
    }
}
