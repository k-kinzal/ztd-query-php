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
     * @param list<string> $parsed Alternatives confirmed in the final SQL's concrete parse tree
     */
    public function __construct(public readonly string $file, public readonly string $input, public readonly string $sql, public readonly array $reached, public readonly array $emitted, public readonly array $parsed = [], public readonly string $mode = '')
    {
    }

    /**
     * Applies the corpus profile identically to both sessions while retaining the fixture's other SQL modes.
     */
    public function executionSql(): string
    {
        return $this->mode === '' ? $this->sql
            : "SET SESSION sql_mode = CONCAT_WS(',', @@SESSION.sql_mode, '" . str_replace("'", "''", $this->mode) . "'); " . $this->sql;
    }
}
