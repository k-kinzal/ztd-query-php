<?php

declare(strict_types=1);

namespace Fuzz\Target;

/**
 * One unmodified sql-faker seed and the statement generated with its supplied profile.
 */
final class Seed
{
    /**
     * Retains the supplied input, generated SQL and the profile needed to execute it.
     */
    public function __construct(public readonly string $file, public readonly string $input, public readonly string $sql, public readonly string $mode = '', public readonly bool $structuralRewrites = true)
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
