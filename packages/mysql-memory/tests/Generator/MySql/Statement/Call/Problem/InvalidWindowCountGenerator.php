<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InvalidWindowCount values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidWindowCount>
 */
final class InvalidWindowCountGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidWindowCount> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind> $function Generated constructor operand
     */
    public function __construct(ErisGenerator $function)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidWindowCount {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidWindowCount(function: $parts[0]);
        }, Generators::tuple(
            $function
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidWindowCount>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Call\\Problem\\InvalidWindowCount' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new InvalidWindowCountGenerator(
            function: \Tests\Generator\MySql\Statement\Call\Window\WindowFunctionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::NthValue])
        )];
    }

}
