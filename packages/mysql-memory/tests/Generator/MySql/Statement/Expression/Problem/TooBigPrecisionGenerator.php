<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes TooBigPrecision values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision>
 */
final class TooBigPrecisionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision> */
    use Composition;

    /**
     * @param ErisGenerator<int> $precision Generated constructor operand
     * @param ErisGenerator<string> $function Generated constructor operand
     * @param ErisGenerator<int>|null $maximum Generated constructor operand
     */
    public function __construct(ErisGenerator $precision, ErisGenerator $function, ?ErisGenerator $maximum = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision(precision: $parts[0], function: $parts[1], maximum: $parts[2]);
        }, Generators::tuple(
            $precision,
            $function,
            $maximum ?? Generators::constant(6)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Expression\\Problem\\TooBigPrecision' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new TooBigPrecisionGenerator(
            precision: Generators::constant(7),
            function: Generators::constant('CAST'),
            maximum: Generators::constant(6)
        )];
    }

}
