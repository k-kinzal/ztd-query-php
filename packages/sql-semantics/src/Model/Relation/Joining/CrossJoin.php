<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation\Joining;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Join;
use SqlSemantics\Model\JoinKind;
use SqlSemantics\Model\TableUse;

/**
 * The Cartesian product of two inputs, without a match predicate.
 *
 * @visibility public
 */
final class CrossJoin extends Join
{
    /**
     * Requires two relational inputs and fixes the operation to a Cartesian product.
     */
    public function __construct(string $id, TableUse|Join $left, TableUse|Join $right, Node $source)
    {
        parent::__construct($id, JoinKind::Cross, $left, $right, $source);
    }

    /**
     * Reconstructs this join with replacement inputs while preserving its join policy.
     */
    #[Override]
    public function withInputs(TableUse|Join $left, TableUse|Join $right): static
    {
        return new static($this->id, $left, $right, $this->source);
    }
}
