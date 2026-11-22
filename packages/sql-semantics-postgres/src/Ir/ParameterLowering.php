<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\PositionalParameter;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * Resolves a numbered parameter projected through the native int4 cast.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ParameterLowering
{
    /**
     * Fixes the cast's builtin target and the already numbered binding.
     *
     * @throws InvalidConstruction When the number does not identify a bound parameter
     */
    public function lower(Scalar $expression, AnalysisContext $context): ?Parameter
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if (!$expression instanceof Cast || $expression->type->setOf) {
            return null;
        }
        $operand = $expression->operand;
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        if (!$operand instanceof PositionalParameter) {
            return null;
        }
        $type = $expression->type->typeFact($context);
        if (!$type instanceof Known || $type->descriptor !== Builtin::Int4) {
            return null;
        }
        $position = $operand->bound($context->profile->grammar);
        Check::input($position !== null, 'The numbered marker must identify a bound PostgreSQL position.');

        return new Parameter($position);
    }
}
