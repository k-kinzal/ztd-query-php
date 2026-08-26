<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation\Upsert;

use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Exception\UnsupportedSqlException;

/**
 * Reads a column off a row the way SQL names one.
 *
 * A column named in an expression and the same column as a driver spells it
 * back need not agree on case, so the lookup does not either. A name nothing
 * answers to is refused: quietly reading it as null would make the assignment
 * that depends on it silently wrong.
 *
 * @phpstan-import-type Row from StatementInterface
 * @phpstan-import-type RowValue from StatementInterface
 */
final class UpsertColumn
{
    /**
     * Answers what a row carries under a column name.
     *
     * @param Row $row Row to read
     * @param string $column Column as the statement named it
     *
     * @return RowValue The value it carries
     *
     * @throws UnsupportedSqlException When the row carries no such column
     */
    public function of(array $row, string $column): int|float|string|bool|null
    {
        foreach ($row as $name => $value) {
            if (strcasecmp($name, $column) === 0) {
                return $value;
            }
        }

        throw new UnsupportedSqlException(
            'unknown UPSERT column ' . $column,
            'Unsupported UPSERT expression',
        );
    }
}
