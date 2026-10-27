<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Event;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RecurringSchedule values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Event\RecurringSchedule>
 */
final class RecurringScheduleGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Event\RecurringSchedule> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $quantity Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit> $unit Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $starts Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $ends Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $quantity, ErisGenerator $unit, ?ErisGenerator $starts = null, ?ErisGenerator $ends = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Event\RecurringSchedule {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Event\RecurringSchedule(quantity: $parts[0], unit: $parts[1], starts: $parts[2], ends: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $quantity,
            $unit,
            $starts ?? Generators::constant(null),
            $ends ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
