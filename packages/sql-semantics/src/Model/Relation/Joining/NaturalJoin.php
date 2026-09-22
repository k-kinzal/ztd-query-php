<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation\Joining;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Join;
use SqlSemantics\Model\JoinKind;
use SqlSemantics\Model\TableUse;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A join whose shared names are derived from the two input schemas.
 *
 * @visibility public
 */
final class NaturalJoin extends Join
{
    /**
     * @param list<SharedColumn> $columns Shared columns in output order
     * @throws InvalidStructure
     */
    public function __construct(string $id, JoinKind $kind, TableUse|Join $left, TableUse|Join $right, public readonly array $columns, Node $source)
    {
        Collections::objects($columns, SharedColumn::class);
        if (count(array_unique(array_column($columns, 'name'))) !== count($columns)) {
            throw new InvalidStructure('A join cannot merge the same column twice.');
        }
        parent::__construct($id, $kind, $left, $right, $source);
    }

    /**
     * Reconstructs this join with replacement inputs while preserving its join policy.
     */
    #[Override]
    public function withInputs(TableUse|Join $left, TableUse|Join $right): static
    {
        return new static($this->id, $this->kind, $left, $right, $this->columns, $this->source);
    }
}
