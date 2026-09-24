<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body\Declaration;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * DECLARE name CURSOR FOR query: a read-only cursor over the query's rows, opened later with OPEN.
 * @visibility public
 * @example Reading a cursor query
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t (n INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE PROCEDURE p() BEGIN DECLARE rows_of_t CURSOR FOR SELECT n FROM t; END');
 *     $statement->body->declarations[0]->query->resultColumns()[0]->name // => 'n'
 */
final class CursorDeclaration
{
    /**
     * Requires the cursor's name and a MySQL query.
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly BoundQuery $query)
    {
        if ($name === '' || $query->origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('A cursor declaration requires a name and a MySQL query.');
        }
    }
}
