<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate;

use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\DistinctTest;

/**
 * Resolves null-safe equality against the exact native signed widths.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class IntegerDistinctnessLowering
{
    /**
     * Resolves both operands without keeping the binding or parser representation.
     */
    public function lower(DistinctTest $test, SourceBinding|JoinBinding $binding, bool $catalogFirst): IntegerDistinctness
    {
        Check::input($catalogFirst, 'A null-safe comparison requires the pg_catalog comparison profile.');
        $operands = new IntegerAtomLowering();
        [$left] = $operands->lower($test->left, $binding, $catalogFirst);
        [$right] = $operands->lower($test->right, $binding, $catalogFirst);

        return new IntegerDistinctness($left, $right, $test->negated);
    }
}
