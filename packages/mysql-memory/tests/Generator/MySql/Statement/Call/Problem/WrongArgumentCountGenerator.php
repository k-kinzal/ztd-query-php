<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes WrongArgumentCount values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount>
 */
final class WrongArgumentCountGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $function Generated constructor operand
     * @param ErisGenerator<int> $arguments Generated constructor operand
     */
    public function __construct(ErisGenerator $function, ErisGenerator $arguments)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount(function: $parts[0], arguments: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $function,
            $arguments
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Call\\Problem\\WrongArgumentCount' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new WrongArgumentCountGenerator(
            function: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::constant('RAND'),
                quote: Generators::constant(null)
            ),
            arguments: Generators::constant(2)
        )];
    }

}
