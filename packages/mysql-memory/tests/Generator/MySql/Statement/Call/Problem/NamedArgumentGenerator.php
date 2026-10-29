<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes NamedArgument values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument>
 */
final class NamedArgumentGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $function Generated constructor operand
     */
    public function __construct(ErisGenerator $function)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument(function: $parts[0]);
        }, Generators::tuple(
            $function
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Call\\Problem\\NamedArgument' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new NamedArgumentGenerator(
            function: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $suffix): string => 'abs' . $suffix, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            )
        )];
    }

}
