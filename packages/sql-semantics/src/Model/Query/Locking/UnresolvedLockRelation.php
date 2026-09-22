<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Locking;

use SqlSemantics\Model\Relation\QualifiedName;

/**
 * A named lock target that could not be bound in the query's relation namespace.
 * @visibility public
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
