<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * An ordinary MySQL statement run from a stored program body, bound with the same semantic forms as direct SQL.
 * @visibility public
 * @example Reading a statement run by a procedure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t (n INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE PROCEDURE p() DELETE FROM t');
 *     $statement->body->statement->kind->value // => 'DELETE'
 */
final class EmbeddedStatement implements ProgramStatement
{
    /**
     * Requires a MySQL statement.
     * @throws InvalidStructure
     */
    public function __construct(public readonly BoundStatement $statement)
    {
        if ($statement->origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('A stored program runs MySQL statements.');
        }
    }
}
