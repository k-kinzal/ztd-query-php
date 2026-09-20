<?php

declare(strict_types=1);

namespace SqlSemantics\Schema;

use SqlSemantics\Dialect;

/**
 * A closed schema snapshot. Missing declarations are errors, not invented tables.
 *
 * @example Reading semantic facts
 *     $parser = new \SqlParser\PostgreSql\PostgreSqlParser();
 *     $analyzer = new \SqlSemantics\Analyzer(\SqlSemantics\Dialect::PostgreSql);
 *     $catalog = $analyzer->schema($parser->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)'));
 *     $catalog->dialect->value // => 'postgresql'
 *
 * @visibility public
 */
final class Catalog
{
    /**
     * @param Dialect $dialect Language used to interpret the declarations
     * @param list<TableDefinition> $tables Tables visible to analysis
     */
    public function __construct(
        public readonly Dialect $dialect,
        public readonly array $tables,
    ) {
    }
}
