<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Expression;

use SqlParser\Parser\Node;

/**
 * A transient traversal frame; no visit becomes part of a semantic value.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ReadingVisit
{
    /**
     * @param Node $node The input production to decode
     * @param bool $view Whether the production belongs to a view body
     * @param bool $after Whether assignment operands have already been visited
     */
    public function __construct(public readonly Node $node, public readonly bool $view = false, public readonly bool $after = false)
    {
    }
}
