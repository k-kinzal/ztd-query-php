<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ReservedFunction values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction>
 */
final class ReservedFunctionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $function Generated constructor operand
     */
    public function __construct(ErisGenerator $function)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction(function: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $function
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Call\\Problem\\ReservedFunction' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new ReservedFunctionGenerator(
            function: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::constant('internal_table_rows'),
                quote: Generators::constant(null)
            )
        )];
    }

}
