<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Conflict;

/**
 * A conflict selected by the declared constraint name.
 * @visibility public
  * @example Inspecting ConstraintConflict
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER,n INTEGER)'));
 *     $conflict = $binder->bind('INSERT INTO t VALUES(1,2) ON CONFLICT ON CONSTRAINT t_key DO UPDATE SET n=excluded.n WHERE t.n<excluded.n')->conflicts[0];
 *     $conflict->target instanceof \SqlSemantics\Model\Write\Conflict\ConstraintConflict // => true
 */
final class ConstraintConflict implements Target
{
    /**
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly string $name)
    {
        if ($name === '') {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A constraint selector requires its name.');
        }
    }
}
