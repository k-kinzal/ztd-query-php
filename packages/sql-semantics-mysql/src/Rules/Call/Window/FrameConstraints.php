<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call\Window;

use SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameCondition;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

/**
 * Describes written frame contradictions without evaluating expressions.
 *
 * A base window may supply an absent ordering. Unused named windows do not
 * inspect literal offset values, but still require a compatible frame shape.
 * Verified on MySQL 8.0.44, 8.4.7 and 9.1.0 through their SQL interfaces.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class FrameConstraints
{
    /**
     * Answers a fixed frame condition; runtime types and parameter values remain open.
     */
    public function condition(WindowSpec $window, bool $used = true): ?FrameCondition
    {
        $frame = $window->frame;
        if ($frame === null) {
            return null;
        }
        $bounds = array_values(array_filter([$frame->start, $frame->end], static fn ($bound): bool => $bound?->offset !== null));
        if ($frame->unit === FrameUnit::Rows && array_filter($bounds, static fn ($bound): bool => $bound->unit !== null) !== []) {
            return FrameCondition::RowsInterval;
        }
        if ($frame->unit === FrameUnit::Range && $bounds !== [] && (count($window->order) > 1 || ($window->order === [] && $window->base === null))) {
            return FrameCondition::RangeOrder;
        }
        if ($used && $frame->unit === FrameUnit::Rows) {
            foreach ($bounds as $bound) {
                if ($bound->offset instanceof NumberLiteral && $bound->offset->form !== NumberForm::Integer) {
                    return FrameCondition::Offset;
                }
            }
        }
        return null;
    }
}
