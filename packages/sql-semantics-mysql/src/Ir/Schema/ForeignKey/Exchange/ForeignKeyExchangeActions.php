<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange;

use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAddition;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemoval;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexRemovalTarget;

/**
 * Detaches an ordered exchange and tags the operands used by its construction checks.
 * @visibility SqlSemantics
 */
final class ForeignKeyExchangeActions
{
    /**
     * Separates the native roles while projecting completed actions in their written order.
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $actions The original closed action candidates
     * @return array{non-empty-list<ForeignKeyAddition|ForeignKeyRemoval|IndexAddition|IndexIdentity>, non-empty-list<ForeignKeyAddition>, list<ForeignKeyRemoval>, array<int, IndexAddition>, list<int>, list<IndexRemovalTarget>}
     */
    public function check(array $actions): array
    {
        Check::input(array_is_list($actions) && $actions !== [], 'A foreign-key exchange needs a nonempty ordered action list.');
        $checked = $additions = $removals = $indexes = $foreignSlots = $physicalRemovals = [];
        foreach ($actions as $position => $action) {
            Check::input(
                $action instanceof ForeignKeyAddition || $action instanceof ForeignKeyRemoval
                || $action instanceof IndexAddition || $action instanceof IndexRemovalTarget,
                'A mixed key alteration requires checked foreign-key or physical index actions.'
            );
            $checked[] = $action instanceof IndexRemovalTarget ? $action->index : $action;
            if ($action instanceof ForeignKeyAddition) {
                $foreignSlots[] = $position;
                $additions[] = $action;
            } elseif ($action instanceof ForeignKeyRemoval) {
                $removals[] = $action;
            } elseif ($action instanceof IndexAddition) {
                $indexes[$position] = $action;
            } else {
                $physicalRemovals[] = $action;
            }
        }
        Check::input($additions !== [] && ($removals !== [] || $indexes !== [] || $physicalRemovals !== []), 'A mixed key alteration requires foreign-key additions and a distinct key edit.');

        return [$checked, $additions, $removals, $indexes, $foreignSlots, $physicalRemovals];
    }
}
