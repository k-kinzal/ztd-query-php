<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation;

/**
 * The namespace and name of a relation, independent of its declaration graph.
 *
 * @visibility public
 * @example Reading the table behind a column reference
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, n INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT a.id FROM t AS a');
 *     $table = $query->outputs[0]->expression->columnBinding()->table;
 *     $table instanceof \SqlSemantics\Model\Relation\TableIdentity // => true
 *     $table->schema // => 'public'
 *     $table->name // => 't'
 */
final class TableIdentity
{
    /**

     */
    public function __construct(public readonly string $schema, public readonly string $name)
    {
    }
}
