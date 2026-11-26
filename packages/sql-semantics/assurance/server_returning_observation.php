<?php

declare(strict_types=1);

/**
 * The fixture grammar admits a target field or target wildcard at each position.
 * Quoted result labels may contain escaped double quotes.
 */
function fixtureReturningClause(string $column, string $qualifier): string
{
    $label = '(?:[A-Za-z_][A-Za-z_0-9]*|"(?:[^"\x00]|"")+")';
    $field = '(?:' . $column . '(?: AS ' . $label . ')?|(?:' . $qualifier . '\\.)?\\*)';

    return '(?: RETURNING ' . $field . '(?:,\\h*' . $field . ')*)?';
}

/**
 * Retrieves positional metadata even when no rows satisfy a mutation predicate.
 */
function observeReturning(PDOStatement $statement, bool $withPostgresTypeIds = false): array
{
    if ($statement->columnCount() === 0) {
        return [];
    }
    $fields = [];
    for ($index = 0; $index < $statement->columnCount(); ++$index) {
        $meta = $statement->getColumnMeta($index);
        $fields[] = $withPostgresTypeIds
            ? [$meta['name'], $meta['native_type'], $meta['pgsql:oid']]
            : [$meta['name'], $meta['native_type']];
    }

    return ['fields' => $fields, 'rows' => $statement->fetchAll(PDO::FETCH_NUM)];
}
