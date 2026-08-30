<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

use ZtdQuery\Schema\Key\ForeignKeyDefinition;

/**
 * One constraint being followed out from a parent, and what to say of it.
 *
 * Following a key needs the constraint itself, the table that holds it, and
 * the statement being simulated: the first two say what to do, and all three
 * say what to tell the caller when the action forbids the statement. They
 * travel together for the whole of one cascade, so they are one thing.
 */
final class FollowedConstraint
{
    /**
     * @param string $childTable Table holding the key
     * @param string $constraintName Constraint being followed, as the schema names it
     * @param ForeignKeyDefinition $foreignKey Constraint being followed
     * @param string $sql Statement being simulated
     */
    public function __construct(
        public readonly string $childTable,
        public readonly string $constraintName,
        public readonly ForeignKeyDefinition $foreignKey,
        public readonly string $sql,
    ) {
    }
}
