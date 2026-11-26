<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate;

use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\BooleanTest;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * Resolves the Boolean operand of a truth test.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class TruthTestLowering
{
    /**
     * Retains the selected test and the checked Boolean operand.
     */
    public function lower(BooleanTest $test, SourceBinding|JoinBinding $binding, bool $catalogFirst): TruthTest
    {
        $operator = TruthOperator::from($test->kind->value);
        $operand = $test->operand;
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        if ($operand instanceof Cast && !$operand->type->setOf) {
            $type = $binding->typeOf($operand);
            $inner = $operand->operand;
            while ($inner instanceof Grouped) {
                $inner = $inner->operand;
            }
            if ($inner instanceof ParsedNull && $type instanceof Known && $type->descriptor === Builtin::Bool) {
                return new TruthTest($operator, new NullLiteral());
            }
        }

        return new TruthTest($operator, (new PredicateLowering())->lowerValue($operand, $binding, $catalogFirst));
    }
}
