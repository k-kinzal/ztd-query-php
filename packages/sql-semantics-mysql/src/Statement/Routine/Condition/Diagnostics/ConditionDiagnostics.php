<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;

/**
 * The information about one condition GET DIAGNOSTICS reads: `CONDITION number item, ...`.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/get-diagnostics.html.
 *
 * @visibility SqlSemantics
 */
final class ConditionDiagnostics implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<InformationItem> The assignments in written order
     */
    public readonly array $items;

    /**
     * @param Scalar $number The number of the condition, counted from 1: a literal, a variable or a name
     * @param list<InformationItem> $items The assignments; at least one, each of a condition information item
     */
    public function __construct(public readonly Scalar $number, array $items)
    {
        $this->items = Check::listOf($items, InformationItem::class, 'GET DIAGNOSTICS reads at least one item.', 1);
        foreach ($this->items as $item) {
            Check::input($item->item instanceof ConditionItemName, 'Condition information holds condition information items.');
        }
    }

    /**
     * Writes CONDITION, the number and the assignments.
     */
    public function render(Output $out): void
    {
        $out->keyword('CONDITION')->node($this->number)->list($this->items);
    }
}
