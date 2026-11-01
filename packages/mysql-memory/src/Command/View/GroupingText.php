<?php

declare(strict_types=1);

namespace MySqlMemory\Command\View;

use SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Statement\Operand;

/**
 * Writes the stored GROUP BY list and its canonical rollup modifier.
 * @visibility MySqlMemory
 */
final class GroupingText
{
    /**
     * @param ExpressionText $expressions The writer of resolved grouping operands
     */
    public function __construct(public readonly ExpressionText $expressions)
    {
    }

    /**
     * Writes no clause for no grouping, or null for a grouping form not stored by the server.
     */
    public function clause(?Grouping $group): ?string
    {
        if ($group === null) {
            return '';
        }
        if ($group->modifier !== null && !in_array($group->modifier, [GroupingModifier::WithRollup, GroupingModifier::Rollup], true)) {
            return null;
        }
        $items = $this->expressions->scalars(array_map(static fn (OrderItem $item): Operand => $item->expression, $group->items));
        return $items === null ? null : ' group by ' . implode(',', $items) . ($group->modifier === null ? '' : ' with rollup');
    }
}
