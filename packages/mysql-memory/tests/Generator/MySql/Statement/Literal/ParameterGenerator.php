<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Literal;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Parameter values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Parameter>
 */
final class ParameterGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Literal\Parameter> */
    use Composition;

    /**
     * @param ErisGenerator<string>|null $marker Generated constructor operand
     * @param ErisGenerator<int>|null $position Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $marker = null, ?ErisGenerator $position = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Literal\Parameter {
            return new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter(marker: $parts[0], position: $parts[1], scalarInformation: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $marker ?? Generators::constant('?'),
            $position ?? Generators::constant(0),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
