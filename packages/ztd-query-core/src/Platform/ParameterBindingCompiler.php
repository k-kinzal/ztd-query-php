<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

/**
 * Writes a statement and its values in the form a driver will take.
 *
 * Drivers disagree about what a placeholder is and whether a value may be
 * bound by name, so a statement ZTD rewrote has to be put into the shape the
 * driver underneath expects before it is run.
 */
interface ParameterBindingCompiler
{
    /**
     * @param array<int|string, mixed>|null $params
     * @return array{sql: string, params: array<int|string, mixed>|null}
     */
    public function compile(string $sql, ?array $params): array;
}
