<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedKeyAlgorithm values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedKeyAlgorithm>
 */
final class RefusedKeyAlgorithmGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedKeyAlgorithm> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> $number Generated constructor operand
     * @param ErisGenerator<int> $offset Generated constructor operand
     */
    public function __construct(ErisGenerator $number, ErisGenerator $offset)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedKeyAlgorithm {
            return new \SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedKeyAlgorithm(number: $parts[0], offset: $parts[1]);
        }, Generators::tuple(
            $number,
            $offset
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedKeyAlgorithm>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Partition\\Problem\\RefusedKeyAlgorithm' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedKeyAlgorithmGenerator(
            number: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                text: Generators::constant(''),
                hexadecimal: Generators::constant(true)
            ),
            offset: Generators::constant(18)
        )];
    }

}
