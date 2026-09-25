<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Recursion;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * PostgreSQL SEARCH {BREADTH | DEPTH} FIRST BY columns SET column: a recursive query adds a column that orders its
 * rows breadth-first or depth-first by the listed columns.
 * @visibility public
 * @example Reading the search clause of a recursive query
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $statement = $binder->bind('WITH RECURSIVE r(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM r WHERE n < 3) SEARCH DEPTH FIRST BY n SET ord SELECT n, ord FROM r ORDER BY ord');
 *     $statement->ctes->definitions[0]->search->order // => \SqlSemantics\Model\Query\Recursion\SearchOrder::DepthFirst
 *     $statement->ctes->definitions[0]->search->sequenceColumn // => 'ord'
 */
final class SearchClause
{
    /**
     * @var non-empty-list<string> CTE columns that order the rows
     */
    public readonly array $columns;

    /**
     * @param list<string> $columns
     * @throws InvalidStructure
     */
    public function __construct(public readonly SearchOrder $order, array $columns, public readonly string $sequenceColumn)
    {
        Collections::strings($columns);
        $this->columns = Collections::nonEmpty($columns);
        if ($sequenceColumn === '') {
            throw new InvalidStructure('A SEARCH clause names its sequence column.');
        }
    }
}
