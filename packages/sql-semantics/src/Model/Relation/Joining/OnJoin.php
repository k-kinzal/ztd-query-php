<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation\Joining;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Join;
use SqlSemantics\Model\JoinKind;
use SqlSemantics\Model\TableUse;

/**
 * A join with a required ON predicate, evaluated before NULL extension.
 *
 * @visibility public
 */
final class OnJoin extends Join
{
    public function __construct(string $id, JoinKind $kind, TableUse|Join $left, TableUse|Join $right, public readonly Expression $condition, Node $source)
    {
        parent::__construct($id, $kind, $left, $right, $source);
    }

    #[Override]
    public function withInputs(TableUse|Join $left, TableUse|Join $right): static
    {
        return new static($this->id, $this->kind, $left, $right, $this->condition, $this->source);
    }
}
