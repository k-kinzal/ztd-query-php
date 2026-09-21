<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

use SqlParser\Parser\Node;

/**
 * A bound SQL statement: sources, targets, row conditions, ordered outputs, and modifiers.
 *
 * @example Reading semantic facts
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT a.id, b.score FROM users a LEFT JOIN users b ON a.id=b.id ORDER BY a.id DESC');
 *     $statement->scopeId // => 's0'
 *
 * @visibility public
 */
class BoundStatement
{
    /**
     * @param string $scopeId Stable scope identity within this bound statement
     * @param TableUse|Join|null $from Logical input, absent for a constant SELECT
     * @param list<TableUse> $relations Table occurrences in binding order
     * @param list<OutputColumn> $outputs Ordered result columns
     * @param Expression|null $where Predicate applied after joins; only TRUE retains a row
     * @param bool $distinct Whether duplicate output tuples are removed
     * @param list<Ordering> $orderBy Ordered sort keys
     * @param Expression|null $limit Maximum row count expression
     * @param Expression|null $offset Number of rows to skip
     * @param Node $source Original statement tree
     * @param list<Expression> $groupBy Grouping expressions
     * @param Expression|null $having Group filter
     * @param array<int|string, BoundStatement> $ctes Local common table expressions
     * @param list<BoundSelect> $branches Compound query operands
     * @param string|null $setOperator Compound operation, including ALL
     * @param array<string, list<Expression>> $clauses Additional expression-bearing clauses
     * @param string $kind Statement operation
     * @param list<TableUse> $targets Written or affected relations
     * @param array<int|string, Expression> $assignments Assigned column values
     * @param list<BoundSelect> $queries Input queries
     * @param list<list<Expression>> $rows Explicit VALUES rows
     * @param bool $withTies Include peers of the final ordered row
     * @param array<string, list<Node>> $syntaxClauses Complete clauses, including non-expression modifiers
     * @param list<\SqlSemantics\Schema\TableDefinition> $declarations Table declarations defined by this statement
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
        public readonly array $groupBy = [],
        public readonly ?Expression $having = null,
        public readonly array $ctes = [],
        public readonly array $branches = [],
        public readonly ?string $setOperator = null,
        public readonly array $clauses = [],
        public readonly string $kind = 'SELECT',
        public readonly array $targets = [],
        public readonly array $assignments = [],
        public readonly array $queries = [],
        public readonly array $rows = [],
        public readonly bool $withTies = false,
        public readonly array $syntaxClauses = [],
        public readonly array $declarations = [],
    ) {
    }
}
