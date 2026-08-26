<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * The stub mysqli field.
 */
final class StubMysqliField
{
    /**
     * Binds the instance to what it will work from.
     *
     * @param string $name
     * @param int $type
     * @param int|string $charsetnr
     */
    public function __construct(
        public string $name,
        public int $type,
        public int|string $charsetnr,
    ) {
    }
}
