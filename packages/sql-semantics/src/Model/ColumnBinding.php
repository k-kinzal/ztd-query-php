<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\TableDefinition;

/**
 * The particular relation occurrence and declared column a reference denotes.
 *
 * @example Reading semantic facts
 *     $parser = new \SqlParser\PostgreSql\PostgreSqlParser();
 *     $analyzer = new \SqlSemantics\Analyzer(\SqlSemantics\Dialect::PostgreSql);
 *     $catalog = $analyzer->schema($parser->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)'));
 *     $query = $analyzer->analyze($parser->parse('SELECT a.id, b.score FROM users a LEFT JOIN users b ON a.id=b.id ORDER BY a.id DESC'), $catalog);
 *     $query->outputs[1]->expression->binding->relationId // => 'r1'
 *
 * @visibility public
 */
final class ColumnBinding
{
    /**
     * @param string $relationId Query-local relation occurrence, such as r0
     * @param TableDefinition $table Table declaration
     * @param ColumnDefinition $column Column declaration
     */
    public function __construct(
        public readonly string $relationId,
        public readonly TableDefinition $table,
        public readonly ColumnDefinition $column,
    ) {
    }
}
