<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes FractionalNumber values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber>
 */
final class FractionalNumberGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> $number Generated constructor operand
     */
    public function __construct(ErisGenerator $number)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber(number: $parts[0]);
        }, Generators::tuple(
            $number
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Server\\Problem\\FractionalNumber' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new FractionalNumberGenerator(
            number: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                text: Generators::constant('18446744073709551616'),
                hexadecimal: Generators::constant(false)
            )
        )];
    }

}
