<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ParameterList values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\ParameterList>
 */
final class ParameterListGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\ParameterList> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Routine\Parameter>> $parameters Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $parameters, ?ErisGenerator $relationInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList(parameters: $parts[0], relationInformation: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $parameters,
            $relationInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
