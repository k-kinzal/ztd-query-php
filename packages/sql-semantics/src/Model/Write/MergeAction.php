<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write;

use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;

/**
 * A match decision; each concrete effect supplies only its own operands.
 * @visibility public
 */
abstract class MergeAction
{
    /**
     * Conditional write operation derived from the concrete action type.
     */
    public readonly Decision\ActionKind $action;

    public function __construct(public readonly Decision\MatchKind $match, public readonly ?Expression $condition, public readonly Node $source)
    {
        $this->action = $this->operation();
    }

    abstract protected function operation(): Decision\ActionKind;
}
