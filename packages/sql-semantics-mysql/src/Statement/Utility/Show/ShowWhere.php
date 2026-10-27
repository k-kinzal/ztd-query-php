<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;

/**
 * The WHERE clause of a SHOW: the rows of the statement for which a condition holds.
 *
 * The condition sees the result columns of the statement and nothing else:
 * a column name resolves to a column of the rows SHOW returns.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/extended-show.html.
 *
 * @visibility public
 * @example Resolving a condition against the result columns
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SHOW VARIABLES WHERE Variable_name = 'port'");
 *     $show->filter->condition->left->scalarInformation()->resolution->slot->name?->value // => 'Variable_name'
 */
final class ShowWhere implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Scalar $condition The condition
     */
    public function __construct(public readonly Scalar $condition, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }

    /**
     * Writes WHERE and the condition.
     */
    public function render(Output $out): void
    {
        $out->keyword('WHERE')->node($this->condition);
    }
}
