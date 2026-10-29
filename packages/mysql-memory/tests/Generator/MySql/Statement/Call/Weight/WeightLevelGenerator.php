<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Weight;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes WeightLevel values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevel>
 */
final class WeightLevelGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevel> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> $number Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Direction|null>|null $direction Generated constructor operand
     * @param ErisGenerator<bool>|null $reverse Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $number, ?ErisGenerator $direction = null, ?ErisGenerator $reverse = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevel {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevel(number: $parts[0], direction: $parts[1], reverse: $parts[2], inputSource: $parts[3]);
        }, Generators::tuple(
            $number,
            $direction ?? Generators::constant(null),
            $reverse ?? Generators::constant(false),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
