<?php

declare(strict_types=1);

namespace Fuzz\Target;

/**
 * One unmodified sql-faker seed, its generated statement, and the grammar alternatives it exercised.
 */
final class Seed
{
    /**
     * @param list<string> $reached Alternatives selected during derivation
     * @param list<string> $emitted Alternatives retained in the emitted statement
     */
    public function __construct(public readonly string $file, public readonly string $input, public readonly string $sql, public readonly array $reached, public readonly array $emitted)
    {
    }
}
