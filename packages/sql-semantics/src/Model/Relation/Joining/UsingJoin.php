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
 * A join with explicit shared names, their input bindings and merged outputs.
 *
 * @visibility public
 */
final class UsingJoin extends Join
{
    /**
     * @var non-empty-list<SharedColumn> Validated ordered operands
     */
    public readonly array $columns;

    /**
     * @param list<SharedColumn> $columns Shared columns in output order
     * @throws InvalidStructure
     */
    public function __construct(string $id, JoinKind $kind, TableUse|Join $left, TableUse|Join $right, array $columns, Node $source)
    {
        Collections::objects($columns, SharedColumn::class);
        if ($columns === []) {
            throw new InvalidStructure('USING requires at least one shared column.');
        }
        if (count(array_unique(array_column($columns, 'name'))) !== count($columns)) {
            throw new InvalidStructure('A join cannot merge the same column twice.');
        }
        parent::__construct($id, $kind, $left, $right, $source);
        $this->columns = Collections::nonEmpty($columns);
    }

    #[Override]
    public function withInputs(TableUse|Join $left, TableUse|Join $right): static
    {
        return new static($this->id, $this->kind, $left, $right, $this->columns, $this->source);
    }
}
