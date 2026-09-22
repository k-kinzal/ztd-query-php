<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Locking;

use SqlSemantics\Model\TableUse;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Applies a locking clause to an explicit nonempty set of relation occurrences.
 * @visibility public
 */
final class NamedRowLock extends RowLock
{
    /**
     * @var non-empty-list<TableUse|UnresolvedLockRelation> Explicit OF targets
     */
    public readonly array $relations;

    /**
     * @param list<TableUse|UnresolvedLockRelation> $relations
     * @throws InvalidStructure
     */
    public function __construct(LockStrength $strength, array $relations, LockWait $wait = LockWait::Wait)
    {
        if ($relations === []) {
            throw new InvalidStructure('An OF clause requires an ordered, nonempty list of lock targets.');
        }
        \SqlSemantics\Model\Validation\Collections::alternatives($relations, [TableUse::class, UnresolvedLockRelation::class]);
        $this->relations = $relations;
        parent::__construct($strength, $wait);
    }
}
