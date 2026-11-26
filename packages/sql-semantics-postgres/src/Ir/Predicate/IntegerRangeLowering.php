<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate;

use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Between;

/**
 * Resolves the comparison overloads and all three pure operands of BETWEEN.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class IntegerRangeLowering
{
    /**
     * Requires a known signed operand in each implicit comparison.
     */
    public function lower(Between $range, SourceBinding|JoinBinding $binding, bool $catalogFirst): IntegerRange
    {
        Check::input($catalogFirst, 'A range predicate must resolve to pg_catalog operators.');
        $operands = new IntegerAtomLowering();
        [$subject, $knownSubject] = $operands->lower($range->operand, $binding, $catalogFirst);
        [$lower, $knownLower] = $operands->lower($range->low, $binding, $catalogFirst);
        [$upper, $knownUpper] = $operands->lower($range->high, $binding, $catalogFirst);
        Check::input($knownSubject || ($knownLower && $knownUpper), 'Each range comparison requires a known signed operand.');

        return new IntegerRange($subject, $lower, $upper, $range->negated, $range->symmetric);
    }
}
