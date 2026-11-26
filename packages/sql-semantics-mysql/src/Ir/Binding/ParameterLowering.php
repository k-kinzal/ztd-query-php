<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Binding;

use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter as ParsedParameter;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;

/**
 * Resolves a named or anonymous marker in a projected integral cast.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ParameterLowering
{
    /**
     * Fixes a marker to its position in the projected parameter sequence.
     */
    public function lower(Scalar $expression, int $position): ?Parameter
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if (!$expression instanceof Cast || $expression->array) {
            return null;
        }
        $kind = $expression->target->kind;
        if ($kind !== CastKind::Signed && $kind !== CastKind::Unsigned) {
            return null;
        }
        $target = $kind === CastKind::Signed ? ParameterTarget::Signed : ParameterTarget::Unsigned;
        $operand = $expression->operand;
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }

        if (!$operand instanceof ParsedParameter) {
            return null;
        }

        return new Parameter($position, $target, $operand->marker === '?' ? null : new ParameterName(substr($operand->marker, 1)));
    }
}
