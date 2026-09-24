<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Declaration;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * An inline SQL body of statements and RETURN steps parsed at definition time and run as one unit (BEGIN ATOMIC ... END).
 * @visibility public
 * @example Reading the body statements
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE PROCEDURE p(v integer) BEGIN ATOMIC INSERT INTO t VALUES (v); END');
 *     count($statement->implementation->body->statements) // => 1
 */
final class AtomicBody implements RoutineBody
{
    /**
     * @param list<BoundStatement|ReturnBody> $statements Body statements and RETURN steps in execution order
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $statements)
    {
        Collections::alternatives($statements, [BoundStatement::class, ReturnBody::class]);
        foreach ($statements as $statement) {
            if ($statement instanceof BoundStatement && $statement->origin->dialect !== Dialect::PostgreSql) {
                throw new InvalidStructure('A routine body contains PostgreSQL statements.');
            }
        }
    }
}
