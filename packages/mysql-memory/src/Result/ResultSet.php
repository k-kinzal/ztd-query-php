<?php

declare(strict_types=1);

namespace MySqlMemory\Result;

use Override;

/**
 * The rows a statement returns, each value in the text the server sends for it.
 *
 * A value is the text of the text protocol, or null for SQL NULL; the binary protocol encodes
 * the same text by the column type. Non-finite floating values retain their IEEE value
 * separately, since their text-protocol spelling is zero.
 *
 * @visibility MySqlMemory
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
     * @param bool $complete Whether the row stream ends successfully; false precedes an execution error
     * @param bool|null $moreResults A stored program's continuation notification; null uses the surrounding response sequence
     * @param array<int, list<ResultSet>> $interruptions Results emitted while computing the row at each offset, before that row's bytes
     * @param array<int, array<int, float>> $binaryValues Non-finite numbers by row and column, before text formatting
     */
    public function __construct(public readonly array $columns, public readonly array $rows, public readonly int $warnings = 0, public readonly bool $complete = true, public readonly ?bool $moreResults = null, public readonly array $interruptions = [], public readonly array $binaryValues = [])
    {
    }

    /**
     * Answers the warning count of the statement.
     */
    #[Override]
    public function warnings(): int
    {
        return $this->warnings;
    }
}
