<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One ordering or grouping expression with the direction written after it.
 *
 * It is the item of ORDER BY, of the GROUP BY of releases that accept a
 * direction there, of GROUP_CONCAT ... ORDER BY, and of the PARTITION BY and
 * ORDER BY of a window. No direction means ascending order; the absence is
 * kept as written. The statement that holds the item derives its expression.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sorting-rows.html.
 *
 * @visibility public
 * @example Holding an ordering expression and its direction
 *     $item = new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('a')), \SqlSemantics\Platform\MySql\Statement\Query\Direction::Descending);
 *     [$item->expression->name->value, $item->direction] // => ['a', \SqlSemantics\Platform\MySql\Statement\Query\Direction::Descending]
 */
final class OrderItem implements Node
{
    use Snapshot;

    /**
     * @param Scalar $expression The ordering expression
     * @param Direction|null $direction The direction, when written
     */
    public function __construct(public readonly Scalar $expression, public readonly ?Direction $direction = null)
    {
    }

    /**
     * Writes the expression and the direction.
     */
    public function render(Output $out): void
    {
        $out->node($this->expression);
        if ($this->direction !== null) {
            $out->keyword($this->direction->value);
        }
    }
}
