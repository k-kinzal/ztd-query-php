<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Window;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes FrameBound values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound>
 */
final class FrameBoundGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $offset Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit|null>|null $unit Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ?ErisGenerator $offset = null, ?ErisGenerator $unit = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound(kind: $parts[0], offset: $parts[1], unit: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $offset ?? Generators::constant(null),
            $unit ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
