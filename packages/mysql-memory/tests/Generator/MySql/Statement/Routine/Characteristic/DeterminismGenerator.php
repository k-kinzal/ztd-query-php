<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Characteristic;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Determinism values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism>
 */
final class DeterminismGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $deterministic Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $deterministic, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism(deterministic: $parts[0], inputSource: $parts[1]);
        }, Generators::tuple(
            $deterministic,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
