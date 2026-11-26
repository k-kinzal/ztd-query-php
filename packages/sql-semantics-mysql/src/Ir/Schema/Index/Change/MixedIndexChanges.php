<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Change;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Batch\IndexRemovalSet;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexDependencyClosure;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexForeignKeyRequirement;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexRemovalTarget;

/**
 * Composes the native placement, removal and rename rules for one alteration.
 * @visibility SqlSemantics
 */
final class MixedIndexChanges
{
    /**
     * Validates roles, table identity, policy and native name invariants together.
     * @param non-empty-list<IndexAlteration> $actions Ordered index actions
     * @param list<IndexRemovalTarget> $bindings Complete original facts in removal order
     * @param list<Identifier> $unmoved Unaffected initial names
     * @param list<IndexForeignKeyRequirement> $requirements Complete native foreign-key sides owning supporting physical keys
     */
    public function check(TableIdentity $owner, array $actions, array $bindings, array $unmoved, IndexChangePolicy $policy, array $requirements): void
    {
        $moves = $drops = [];
        $removals = new IndexRemovalSet();
        $coordinate = $removals->coordinate($owner);
        foreach ($actions as $action) {
            $operation = $action->operation;
            if ($operation instanceof IndexIdentity) {
                Check::input($removals->coordinate($operation->owner) === $coordinate, 'Mixed index changes require one owning table and lifetime.');
                $bound = $bindings[count($drops)] ?? null;
                Check::input(
                    $bound !== null
                    && $removals->coordinate($bound->index->owner) === $coordinate
                    && $bound->index->name->value === $operation->name->value
                    && $bound->index->role === $operation->role,
                    'An original removal binding must match the exact ordered index identity.'
                );
                $drops[] = $bound;
            } else {
                $moves[] = $operation;
            }
        }
        Check::input($drops !== [] && $moves !== [], 'A mixed index alteration requires at least one removal and one rename.');
        Check::input(count($drops) === count($bindings), 'A mixed index alteration has one original binding per removal.');
        $identities = $removals->check($drops, $policy);
        (new IndexDependencyClosure())->check($identities, $requirements);
        (new MixedIndexNamespace())->check($drops, $moves, $unmoved);
    }
}
