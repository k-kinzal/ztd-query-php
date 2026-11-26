<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The statement information GET DIAGNOSTICS reads: the number of conditions and the affected-rows count.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/get-diagnostics.html.
 *
 * @visibility SqlSemantics
 */
final class StatementDiagnostics implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<InformationItem> The assignments in written order
     */
    public readonly array $items;

    /**
     * @param list<InformationItem> $items The assignments; at least one, each of a statement information item
     */
    public function __construct(array $items)
    {
        $this->items = Check::listOf($items, InformationItem::class, 'GET DIAGNOSTICS reads at least one item.', 1);
        foreach ($this->items as $item) {
            Check::input($item->item instanceof StatementItemName, 'Statement information holds statement information items.');
        }
    }

    /**
     * Writes the assignments.
     */
    public function render(Output $out): void
    {
        $out->list($this->items);
    }
}
