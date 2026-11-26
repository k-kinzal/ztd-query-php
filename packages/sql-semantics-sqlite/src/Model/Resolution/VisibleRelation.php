<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Resolution;

use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Relation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\RowShape;

/**
 * A relation occurrence as one position of a query sees it: its shape and the names that qualify it.
 *
 * An occurrence with neither alias nor name, such as the merged columns of a
 * join, is found by unqualified names only.
 *
 * @visibility SqlSemantics
 */
final class VisibleRelation
{
    use \SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

    /**
     * @param Relation $relation The occurrence
     * @param RowShape $shape The row shape visible at this position
     * @param Name|null $alias The correlation name; when present it is the only qualifier
     * @param QualifiedName|null $name The relation name that qualifies the occurrence when it has no alias
     * @param list<int> $hidden The slot positions an unqualified name and a star do not see, such as the duplicate of a merged join column
     * @param list<ImplicitSlot> $implicit The slots found by name only
     * @param array<int, ResolvedColumn> $representatives The values represented by merged positions
     */
    public function __construct(
        public readonly Relation $relation,
        public readonly RowShape $shape,
        public readonly ?Name $alias = null,
        public readonly ?QualifiedName $name = null,
        public readonly array $hidden = [],
        public readonly array $implicit = [],
        public readonly array $representatives = [],
    ) {
    }

    /**
     * Binds a visible position, selecting a merged representative only for an unqualified lookup.
     */
    public function resolved(int $position, bool $merged, int $depth = 0): ResolvedColumn
    {
        $value = $merged ? ($this->representatives[$position] ?? null) : null;

        return $value === null ? new ResolvedColumn($this->relation, $this->shape->slots[$position], $depth)
            : new ResolvedColumn($value->relation, $value->slot, $depth, $value->merge);
    }

    /**
     * Projects the occurrence heading with the value facts for a wildcard position.
     */
    public function starSlot(int $position): OutputSlot
    {
        $heading = $this->shape->slots[$position];
        $value = $this->representatives[$position]->slot ?? null;

        return $value === null ? $heading
            : new OutputSlot($heading->name, $value->type, $value->nullability, null, $value, $heading->unnamed);
    }
}
