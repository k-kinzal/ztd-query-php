<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CastSizeInput values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Problem\CastSizeInput>
 */
final class CastSizeInputGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\Problem\CastSizeInput> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize> $problem Generated constructor operand
     * @param ErisGenerator<int> $start Generated constructor operand
     * @param ErisGenerator<int> $end Generated constructor operand
     */
    public function __construct(ErisGenerator $problem, ErisGenerator $start, ErisGenerator $end)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\Problem\CastSizeInput {
            return new \SqlSemantics\Platform\MySql\Statement\Type\Problem\CastSizeInput(problem: $parts[0], start: $parts[1], end: $parts[2]);
        }, Generators::tuple(
            $problem,
            $start,
            $end
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Problem\CastSizeInput>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Type\\Problem\\CastSizeInput' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new CastSizeInputGenerator(
            problem: new InvalidTypeSizeGenerator(
                rule: TypeLimitGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit::Precision]),
                name: Generators::constant(''),
                actual: Generators::constant(66),
                maximum: Generators::constant(65),
                scope: Generators::constant(null)
            ),
            start: Generators::constant(18),
            end: Generators::constant(22)
        )];
    }

}
