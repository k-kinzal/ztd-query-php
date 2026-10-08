<?php

declare(strict_types=1);

namespace MySqlMemory\System;

/**
 * The rows of one system table, computed from the state of the server when a statement reads the table.
 *
 * @visibility MySqlMemory
 */
interface SystemRows
{
    /**
     * Answers the rows of the table, each a value by column name; a column a row does not name is NULL.
     *
     * @return list<array<string, int|float|string|null>>
     *
     * @throws \MySqlMemory\Error\SqlError When the release refuses to read the table
     */
    public function rows(Reading $reading): array;
}
