<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UndeclaredVariable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable>
 */
final class UndeclaredVariableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     */
    public function __construct(ErisGenerator $name)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable(name: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $name
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Query\\Problem\\UndeclaredVariable' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new UndeclaredVariableGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $suffix): string => 'y' . $suffix, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            )
        )];
    }

}
