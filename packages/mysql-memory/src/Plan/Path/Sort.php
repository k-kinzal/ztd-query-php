<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path;

use MySqlMemory\Typing\Domain;

/**
 * Orders the rows of its input by values at positions of the row.
 *
 * NULL sorts before every value in ascending order. Rows with equal keys keep their input order.
 *
 * @visibility MySqlMemory
 */
final class Sort implements AccessPath
{
    /**
     * @param AccessPath $input The rows sorted
     * @param list<array{int, Domain, bool}> $keys The position, domain and whether descending, of each key
     */
    public function __construct(public readonly AccessPath $input, public readonly array $keys)
    {
    }

    /**
     * Answers the width of the input.
     */
    #[\Override]
    public function width(): int
    {
        return $this->input->width();
    }
}
