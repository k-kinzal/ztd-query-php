<?php

declare(strict_types=1);

namespace MySqlMemory\Result;

/**
 * The rows a statement returns, each value in the text the server sends for it.
 *
 * A value is the text of the text protocol, or null for SQL NULL; the binary protocol encodes
 * the same text by the column type.
 *
 * @visibility public
 * @example Reading the rows of a query
 *     $session = (new \MySqlMemory\Instance())->connect();
 *     $result = $session->query('SELECT 1 + 1 AS two, NULL AS nothing')[0];
 *     [$result->columns[0]->name, $result->rows] // => ['two', [['2', null]]]
 */
final class ResultSet implements Reply
{
    /**
     * @param list<ResultColumn> $columns The column definitions in order
     * @param list<list<string|null>> $rows The rows in the order they are sent
     * @param int $warnings The number of warnings the statement raised
     */
    public function __construct(public readonly array $columns, public readonly array $rows, public readonly int $warnings = 0)
    {
    }

    /**
     * Answers the warning count of the statement.
     */
    #[\Override]
    public function warnings(): int
    {
        return $this->warnings;
    }
}
