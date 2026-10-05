<?php

declare(strict_types=1);

namespace SqlSemantics\Resolution;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Shape\RowShape;

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
    use \SqlSemantics\Statement\Snapshot;

    /**
     * @param Relation $relation The occurrence
     * @param RowShape $shape The row shape visible at this position
     * @param Name|null $alias The correlation name; when present it is the only qualifier
     * @param QualifiedName|null $name The relation name that qualifies the occurrence when it has no alias
     * @param list<int> $hidden The slot positions an unqualified name and a star do not see, such as the duplicate of a merged join column
     * @param list<ImplicitSlot> $implicit The slots found by name only
     */
    public function __construct(
        public readonly Relation $relation,
        public readonly RowShape $shape,
        public readonly ?Name $alias = null,
        public readonly ?QualifiedName $name = null,
        public readonly array $hidden = [],
        public readonly array $implicit = [],
    ) {
    }
}
