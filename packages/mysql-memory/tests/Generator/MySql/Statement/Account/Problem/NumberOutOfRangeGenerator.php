<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes NumberOutOfRange values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\NumberOutOfRange>
 */
final class NumberOutOfRangeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Problem\NumberOutOfRange> */
    use Composition;

    /**
     * @param ErisGenerator<string> $option Generated constructor operand
     * @param ErisGenerator<string> $number Generated constructor operand
     * @param ErisGenerator<string> $error Generated constructor operand
     */
    public function __construct(ErisGenerator $option, ErisGenerator $number, ErisGenerator $error)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Problem\NumberOutOfRange {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\NumberOutOfRange(option: $parts[0], number: $parts[1], error: $parts[2]);
        }, Generators::tuple(
            $option,
            $number,
            $error
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\NumberOutOfRange>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Account\\Problem\\NumberOutOfRange' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new NumberOutOfRangeGenerator(
            option: Generators::constant('thread id'),
            number: Generators::constant('1.5'),
            error: Generators::constant('ER_ONLY_INTEGERS_ALLOWED')
        )];
    }

}
