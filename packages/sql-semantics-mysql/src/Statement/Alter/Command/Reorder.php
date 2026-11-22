<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;

/**
 * `ORDER BY column [ASC | DESC], …`: a request to store the rows in an order once, when the table is rebuilt.
 *
 * Mirrors PT_alter_table_order. Each item names a column of the table (the
 * grammar admits only column names) and is resolved in the scope of the
 * changed table.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-order-by.
 *
 * @visibility SqlSemantics
 */
final class Reorder implements AlterCommand
{
    use Snapshot;

    /**
     * @var list<OrderItem> The ordering columns in order; at least one
     */
    public readonly array $items;

    /**
     * @param list<OrderItem> $items The ordering columns in order; at least one
     */
    public function __construct(array $items)
    {
        Check::input($items !== [], 'ORDER BY names at least one column.');
        $this->items = Check::listOf($items, OrderItem::class, 'ORDER BY holds a list of ordering items.');
        foreach ($this->items as $item) {
            Check::input($item->expression instanceof ColumnUse, 'ALTER TABLE orders by column names only.');
        }
    }

    /**
     * Resolves the ordering columns in the scope of the table.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
        foreach ($this->items as $item) {
            $derivation->scalar($item->expression, $scope);
        }
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ORDER', 'BY')->list($this->items);
    }
}
