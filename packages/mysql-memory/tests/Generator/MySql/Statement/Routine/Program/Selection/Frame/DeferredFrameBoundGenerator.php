<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Selection\Frame;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredFrameBound values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrameBound>
 */
final class DeferredFrameBoundGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrameBound> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $offset Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit> $unit Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ErisGenerator $offset, ErisGenerator $unit, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrameBound {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrameBound(kind: $parts[0], offset: $parts[1], unit: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $offset,
            $unit,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
