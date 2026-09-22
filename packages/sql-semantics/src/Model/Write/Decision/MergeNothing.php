<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Decision;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;

/**
 * Typed MergeNothing effect.
 * @visibility public
 */
final class MergeNothing extends \SqlSemantics\Model\Write\MergeAction
{
    /**

     */
    public function __construct(MatchKind $match, ?Expression $condition, Node $source)
    {
        parent::__construct($match, $condition, $source);
    }

    #[Override]
    protected function operation(): ActionKind
    {
        return ActionKind::Nothing;
    }
}
