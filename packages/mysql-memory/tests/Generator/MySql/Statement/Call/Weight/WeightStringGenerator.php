<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Weight;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes WeightString values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString>
 */
final class WeightStringGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $subject Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast|null>|null $cast Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null>|null $length Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevel>>|null $levels Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevelRange|null>|null $range Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $subject, ?ErisGenerator $cast = null, ?ErisGenerator $length = null, ?ErisGenerator $levels = null, ?ErisGenerator $range = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString(subject: $parts[0], cast: $parts[1], length: $parts[2], levels: $parts[3], range: $parts[4], scalarInformation: $parts[5], inputSource: $parts[6]);
        }, \Tests\Generator\Operands::tuple(
            $subject,
            $cast ?? Generators::constant(null),
            $length ?? Generators::constant(null),
            $levels ?? Generators::constant([]),
            $range ?? Generators::constant(null),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
