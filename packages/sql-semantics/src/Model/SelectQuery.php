<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

use SqlParser\Parser\Node;

/**
 * The bound logical query: sources, row conditions, ordered outputs, and result modifiers.
 *
 * @example Reading semantic facts
 *     $parser = new \SqlParser\PostgreSql\PostgreSqlParser();
 *     $analyzer = new \SqlSemantics\Analyzer(\SqlSemantics\Dialect::PostgreSql);
 *     $catalog = $analyzer->schema($parser->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)'));
 *     $query = $analyzer->analyze($parser->parse('SELECT a.id, b.score FROM users a LEFT JOIN users b ON a.id=b.id ORDER BY a.id DESC'), $catalog);
 *     $query->scopeId // => 's0'
 *
 * @visibility public
 */
final class SelectQuery
{
    /**
     * @param string $scopeId Stable scope identity within this analysis
     * @param TableUse|Join|null $from Logical input, absent for a constant SELECT
     * @param list<TableUse> $relations Table occurrences in binding order
     * @param list<OutputColumn> $outputs Ordered result columns
     * @param Expression|null $where Predicate applied after joins; only TRUE retains a row
     * @param bool $distinct Whether duplicate output tuples are removed
     * @param list<Ordering> $orderBy Ordered sort keys
     * @param Expression|null $limit Maximum row count expression
     * @param Expression|null $offset Number of rows to skip
     * @param Node $source Original SELECT tree
     */
    public function __construct(
        public readonly string $scopeId,
        public readonly TableUse|Join|null $from,
        public readonly array $relations,
        public readonly array $outputs,
        public readonly ?Expression $where,
        public readonly bool $distinct,
        public readonly array $orderBy,
        public readonly ?Expression $limit,
        public readonly ?Expression $offset,
        public readonly Node $source,
    ) {
    }
}
