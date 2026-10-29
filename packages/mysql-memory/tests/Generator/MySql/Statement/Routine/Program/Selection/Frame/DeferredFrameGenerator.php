<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Selection\Frame;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredFrame values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrame>
 */
final class DeferredFrameGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrame> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit> $unit Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrameBound> $start Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrameBound|null>|null $end Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameExclusion|null>|null $exclusion Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $unit, ErisGenerator $start, ?ErisGenerator $end = null, ?ErisGenerator $exclusion = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrame {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrame(unit: $parts[0], start: $parts[1], end: $parts[2], exclusion: $parts[3], inputSource: $parts[4]);
        }, Generators::tuple(
            $unit,
            $start,
            $end ?? Generators::constant(null),
            $exclusion ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
