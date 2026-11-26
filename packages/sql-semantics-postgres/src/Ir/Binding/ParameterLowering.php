<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Binding;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CastDestination;
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
 * Resolves explicit positional parameter casts in native projection order.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ParameterLowering
{
    /**
     * Resolves a projection's cast chain against the statement's input slots.
     *
     * @param array<int, CastDestination> $inputTypes Inferred input slots in statement traversal order
     * @throws InvalidConstruction When the number or conversion has no native binding
     */
    public function lower(Scalar $expression, AnalysisContext $context, array &$inputTypes = []): Parameter|BuiltinCast|null
    {
        $casts = [];
        while ($expression instanceof Grouped || $expression instanceof Cast) {
            if ($expression instanceof Cast) {
                $casts[] = $expression;
            }
            $expression = $expression->operand;
        }
        if (!$expression instanceof PositionalParameter || $casts === []) {
            return null;
        }
        $targets = [];
        foreach (array_reverse($casts) as $cast) {
            $target = $this->target($cast, $context);
            if ($target === null) {
                return null;
            }
            $targets[] = $target;
        }
        $position = $expression->bound($context->profile->grammar);
        Check::input($position !== null, 'The numbered marker must identify a bound PostgreSQL position.');
        $input = $inputTypes[$position] ??= $targets[0];
        $value = new Parameter($position, $input);
        foreach ($targets as $index => $target) {
            if ($index !== 0 || $target !== $input) {
                $value = new BuiltinCast($target, $value);
            }
        }

        return $value;
    }

    /**
     * Finds this cast's exact native parameter or conversion type.
     */
    public function target(Cast $cast, AnalysisContext $context): ?CastDestination
    {
        $type = $cast->type->typeFact($context);
        if ($cast->type->setOf || !$type instanceof Known) {
            return null;
        }

        return match ($type->descriptor) {
            Builtin::Int4 => CastDestination::Integer,
            Builtin::Int8 => CastDestination::WideInteger,
            Builtin::Bool => CastDestination::Boolean,
            Builtin::Text => CastDestination::Text,
            default => null,
        };
    }
}
