<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes OperandColumns values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns>
 */
final class OperandColumnsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns> */
    use Composition;

    /**
     * @param ErisGenerator<int> $expected Generated constructor operand
     * @param ErisGenerator<int> $actual Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $expression Generated constructor operand
     */
    public function __construct(ErisGenerator $expected, ErisGenerator $actual, ?ErisGenerator $expression = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns(expected: $parts[0], actual: $parts[1], expression: $parts[2]);
        }, Generators::tuple(
            $expected,
            $actual,
            $expression ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Expression\\Problem\\OperandColumns' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new OperandColumnsGenerator(
            expected: Generators::constant(2),
            actual: Generators::constant(3),
            expression: Generators::constant(null)
        )];
    }

}
