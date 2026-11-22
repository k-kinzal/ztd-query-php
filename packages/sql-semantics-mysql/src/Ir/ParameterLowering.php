<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter as ParsedParameter;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;

/**
 * Resolves a native marker in a projected signed cast.
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
        if (!$expression instanceof Cast || $expression->array || $expression->target->kind !== CastKind::Signed) {
            return null;
        }
        $operand = $expression->operand;
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }

        return $operand instanceof ParsedParameter && $operand->marker === '?' ? new Parameter($position) : null;
    }
}
