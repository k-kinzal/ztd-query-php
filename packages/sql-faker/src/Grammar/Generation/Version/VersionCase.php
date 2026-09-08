<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Version;

use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;

/**
 * An immutable association of reviewed releases and a shared generator definition.
 */
final class VersionCase
{
    /**
     * @param non-empty-list<string> $versions
     */
    public function __construct(
        public readonly array $versions,
        public readonly LexemeGenerator $generator,
        public readonly string $id,
    ) {
    }
}
