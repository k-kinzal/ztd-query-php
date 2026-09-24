<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Locking;

use SqlSemantics\Model\Relation\QualifiedName;

/**
 * A named lock target that could not be bound in the query's relation namespace.
 * @visibility public
 * @example Reading a lock target that does not resolve
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT id FROM t FOR UPDATE OF missing', strict: false);
 *     $statement->locks[0]->relations[0]->name->parts // => ['missing']
 */
final class UnresolvedLockRelation
{
    /**
     * Retains an identifier for a failed relation lookup, never an arbitrary SQL fragment.
     */
    public function __construct(public readonly QualifiedName $name)
    {
    }
}
