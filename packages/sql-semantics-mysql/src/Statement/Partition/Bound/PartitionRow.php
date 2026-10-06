<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Bound;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A parenthesized row of partition values: expressions and MAXVALUE.
 *
 * Mirrors PT_part_value_item_list_paren. Each expression is a constant the
 * server evaluates when the table is defined; it is derived in a scope with
 * no visible relation, so a column name in it is a diagnostic.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-columns.html.
 *
 * @visibility public
 * @example Holding a row of partition values
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('10'), new \SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionMaximum()]);
 *     count($row->items) // => 2
 */
final class PartitionRow implements Node
{
    use Snapshot;

    /**
     * @var list<Scalar|PartitionMaximum> The values in order; at least one
     */
    public readonly array $items;

    /**
     * @param list<Scalar|PartitionMaximum> $items The values in order; at least one
     */
    public function __construct(array $items)
    {
        $list = [];
        foreach (Check::listOf($items, Node::class, 'A row of partition values holds at least one value.', 1) as $item) {
            Check::input($item instanceof Scalar || $item instanceof PartitionMaximum, 'A partition value is an expression or MAXVALUE.');
            $list[] = $item;
        }
        $this->items = $list;
    }

    /**
     * Derives the expressions as constants.
     */
    public function deriveRow(Derivation $derivation): void
    {
        foreach ($this->items as $item) {
            if ($item instanceof Scalar) {
                $derivation->scalar($item, $derivation->environment());
            }
        }
    }

    /**
     * Writes the parenthesized values.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->list($this->items)->symbol(')');
    }
}
