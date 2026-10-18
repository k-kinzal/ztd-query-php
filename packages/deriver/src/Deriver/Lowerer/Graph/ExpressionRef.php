<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Graph;

/**
 * A stable ExpressionRef within a analysis snapshot.
 *
 * @visibility root
 * @example Creating a reference
 *     $ref = new \Deriver\Deriver\Lowerer\Graph\ExpressionRef(new \Deriver\Deriver\Sources\SourceRef('s', 'a.php', 0, 1), 'run', 'r1');
 *     $ref->callable // => 'run'
 */
final class ExpressionRef
{
    /**
     * @param \Deriver\Deriver\Sources\SourceRef $source Range of the original expression
     * @param string $callable Owning callable identity
     * @param string $register SSA result register
     */
    public function __construct(
        public readonly \Deriver\Deriver\Sources\SourceRef $source,
        public readonly string $callable,
        public readonly string $register,
    ) {
    }
}
