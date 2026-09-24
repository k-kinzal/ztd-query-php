<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write;

use SqlParser\Parser\Node;

/**
 * An assignment whose concrete form determines the required input and destination shape.
 *
 * @visibility public
 * @example Counting an assignment's destinations
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('UPDATE t SET id=1');
 *     count($statement->writes[0]->destinations()) // => 1
 */
abstract class Assignment
{
    /**
     * Records the diagnostic origin of the assignment.
     */
    public function __construct(public readonly Node $source)
    {
    }

    /**
     * @return non-empty-list<Storage\Path>
     */
    abstract public function destinations(): array;
}
