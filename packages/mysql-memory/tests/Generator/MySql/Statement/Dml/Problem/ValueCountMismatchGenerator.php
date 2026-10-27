<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ValueCountMismatch values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\ValueCountMismatch>
 */
final class ValueCountMismatchGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\ValueCountMismatch> */
    use Composition;

    /**
     * @param ErisGenerator<int> $expected Generated constructor operand
     * @param ErisGenerator<int> $actual Generated constructor operand
     * @param ErisGenerator<int|null>|null $row Generated constructor operand
     */
    public function __construct(ErisGenerator $expected, ErisGenerator $actual, ?ErisGenerator $row = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Problem\ValueCountMismatch {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\ValueCountMismatch(expected: $parts[0], actual: $parts[1], row: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $expected,
            $actual,
            $row ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\ValueCountMismatch>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml\\Problem\\ValueCountMismatch' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new ValueCountMismatchGenerator(
            expected: Generators::constant(1),
            actual: Generators::constant(2),
            row: Generators::constant(1)
        )];
    }

}
