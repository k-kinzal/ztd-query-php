<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Weight;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes WeightLevelRange values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevelRange>
 */
final class WeightLevelRangeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevelRange> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> $from Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> $to Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $from, ErisGenerator $to, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevelRange {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevelRange(from: $parts[0], to: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $from,
            $to,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
