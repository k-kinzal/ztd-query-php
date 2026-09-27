<?php

declare(strict_types=1);

namespace Deriver\Reference;

/**
 * A stable ExpressionRef within a project snapshot.
 *
 * @visibility public
 * @example Creating a reference
 *     $ref = new \Deriver\Reference\ExpressionRef(new \Deriver\Reference\SourceRef('s', 'a.php', 0, 1), 'run', 'r1');
 *     $ref->callable // => 'run'
 */
final class ExpressionRef
{
    /**
     * @param SourceRef $source Range of the original expression
     * @param string $callable Owning callable identity
     * @param string $register SSA result register
     */
    public function __construct(
        public readonly SourceRef $source,
        public readonly string $callable,
        public readonly string $register,
    ) {
    }
}
