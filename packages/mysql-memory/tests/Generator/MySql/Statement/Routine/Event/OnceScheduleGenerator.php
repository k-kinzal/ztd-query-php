<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Event;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes OnceSchedule values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Event\OnceSchedule>
 */
final class OnceScheduleGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Event\OnceSchedule> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $at Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $at, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Event\OnceSchedule {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Event\OnceSchedule(at: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $at,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
