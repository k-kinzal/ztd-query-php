<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Generation;

use RuntimeException;

/**
 * A relation walk would revisit a row role without an explicit reuse rule.
 */
final class RecursiveRelationException extends RuntimeException
{
    /**
     * @param list<string> $path
     */
    public function __construct(array $path, string $table)
    {
        parent::__construct('Recursive fixture traversal: ' . implode(' -> ', [...$path, $table]) . '. Multiple roles or shared rows require explicit references.');
    }
}
