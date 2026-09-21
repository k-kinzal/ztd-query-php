<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\TableDefinition;

/**
 * The particular relation occurrence and declared column a reference denotes.
 *
 * @example Reading semantic facts
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT a.id, b.score FROM users a LEFT JOIN users b ON a.id=b.id ORDER BY a.id DESC');
 *     $statement->outputs[1]->expression->binding->relationId // => 'r1'
 *
 * @visibility public
 */
final class ColumnBinding
{
    /**
     * @param string $relationId Query-local relation occurrence, such as r0
     * @param TableDefinition $table Table declaration
     * @param ColumnDefinition $column Column declaration
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly string $relationId,
        public readonly TableDefinition $table,
        public readonly ColumnDefinition $column,
    ) {
        if ($relationId === '' || !in_array($column, $table->columns, true)) {
            throw new InvalidStructure('A column binding must refer to a member of its declaration.');
        }
    }
}
