<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type;

use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * Where a new enum label goes: BEFORE or AFTER an existing label.
 *
 * Without a position the label is added at the end of the sort order.
 * Source: https://www.postgresql.org/docs/17/sql-altertype.html.
 *
 * @visibility public
 * @example Placing a label after another
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\EnumPosition(true, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('ok')))->after // => true
 */
final class EnumPosition implements Node
{
    use Snapshot;

    /**
     * @param bool $after Whether the label goes after the neighbor instead of before it
     * @param StringConstant $neighbor The existing label
     */
    public function __construct(public readonly bool $after, public readonly StringConstant $neighbor)
    {
    }

    /**
     * Writes BEFORE or AFTER and the neighbor.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->after ? 'AFTER' : 'BEFORE')->node($this->neighbor);
    }
}
