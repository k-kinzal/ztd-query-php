<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Model;

use SqlParser\Parser\Node;

/**
 * A join with its own match predicate and NULL extension identity.
 *
 * @example Accept this semantic value in a database-independent consumer
 *     $consume = static fn (\SqlSemantics\Core\Model\Join $value): string => $value::class;
 *     $consume instanceof \Closure // => true
 *
 * @visibility public
 */
final class Join
{
    /**
     * @param string $id Query-local join identity
     * @param JoinKind $kind Join operation
     * @param TableUse|Join $left Left input
     * @param TableUse|Join $right Right input
     * @param Expression|null $condition Match predicate, evaluated before this join extends NULLs
     * @param Node $source Original join syntax
     */
    public function __construct(
        public readonly string $id,
        public readonly JoinKind $kind,
        public readonly TableUse|self $left,
        public readonly TableUse|self $right,
        public readonly ?Expression $condition,
        public readonly Node $source,
    ) {
    }
}
