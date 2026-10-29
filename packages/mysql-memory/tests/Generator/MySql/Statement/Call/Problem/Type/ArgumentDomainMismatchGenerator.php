<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ArgumentDomainMismatch values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\Type\ArgumentDomainMismatch>
 */
final class ArgumentDomainMismatchGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Problem\Type\ArgumentDomainMismatch> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $function Generated constructor operand
     * @param ErisGenerator<string> $argument Generated constructor operand
     */
    public function __construct(ErisGenerator $function, ErisGenerator $argument)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Problem\Type\ArgumentDomainMismatch {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Problem\Type\ArgumentDomainMismatch(function: $parts[0], argument: $parts[1]);
        }, Generators::tuple(
            $function,
            $argument
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\Type\ArgumentDomainMismatch>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Call\\Problem\\Type\\ArgumentDomainMismatch' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new ArgumentDomainMismatchGenerator(
            function: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::constant('st_pointfromgeohash'),
                quote: Generators::constant(null)
            ),
            argument: Generators::constant('SRID')
        )];
    }

}
