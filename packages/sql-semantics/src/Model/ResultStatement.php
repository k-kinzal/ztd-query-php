<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

/**
 * An operation producing named result columns.
 * @visibility public
 * @example Reading the result columns of a mutation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('DELETE FROM t RETURNING id');
 *     $statement instanceof \SqlSemantics\Model\ResultStatement // => true
 *     $statement->resultColumns()[0]->name // => 'id'
 */
interface ResultStatement
{
    /**
     * @return list<OutputColumn>
     */
    public function resultColumns(): array;


}
