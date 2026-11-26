<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Predicate\Truth;

use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\Literal\IntegerTokenLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\TruthTest as ParsedTruthTest;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

/**
 * Resolves truth operands within the checked signed and predicate domains.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TruthLowering
{
    /**
     * Resolves both the truth operation and the local operand.
     */
    public function lower(ParsedTruthTest $test, SourceBinding|JoinBinding $binding): TruthTest
    {
        $operation = TruthOperator::from(($test->negated ? 'NOT ' : '') . $test->truth->value);
        $operand = $test->operand;
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        if ($operand instanceof ColumnUse) {
            return new TruthTest($operation, $binding->membershipReference($operand));
        }
        $token = (new IntegerTokenLowering())->lower($operand);
        if ($token !== null) {
            return new TruthTest($operation, new RangeIntegerLiteral($token[0], $token[1], $token[2]));
        }

        return new TruthTest($operation, (new PredicateLowering())->lowerValue($operand, $binding));
    }
}
