<?php

declare(strict_types=1);

namespace SqlSemantics\Schema;

use SqlParser\Parser\Node;

/**
 * A declared integrity condition, retaining its complete syntax for downstream consumers.
 *
 * @example Reading semantic facts
 *     $parser = new \SqlParser\PostgreSql\PostgreSqlParser();
 *     $analyzer = new \SqlSemantics\Analyzer(\SqlSemantics\Dialect::PostgreSql);
 *     $catalog = $analyzer->schema($parser->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)'));
 *     $catalog->tables[0]->constraints[0]->columns // => ['id']
 *
 * @visibility public
 */
final class TableConstraint
{
    /**
     * @param ConstraintKind $kind Integrity condition
     * @param list<string> $columns Local columns, in declaration order
     * @param Node $source Complete constraint syntax, including actions and deferrability
     * @param string|null $name Explicit constraint name
     * @param list<string> $referencedTable Qualified foreign table name
     * @param list<string> $referencedColumns Referenced key columns; empty means the primary key
     * @param Node|null $expression CHECK expression syntax
     */
    public function __construct(
        public readonly ConstraintKind $kind,
        public readonly array $columns,
        public readonly Node $source,
        public readonly ?string $name = null,
        public readonly array $referencedTable = [],
        public readonly array $referencedColumns = [],
        public readonly ?Node $expression = null,
    ) {
    }
}
