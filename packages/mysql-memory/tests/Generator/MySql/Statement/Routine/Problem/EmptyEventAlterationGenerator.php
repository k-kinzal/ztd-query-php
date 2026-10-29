<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes EmptyEventAlteration values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\EmptyEventAlteration>
 */
final class EmptyEventAlterationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\EmptyEventAlteration> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account|null>|null $definer Generated constructor operand
     * @param ErisGenerator<int>|null $offset Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $definer = null, ?ErisGenerator $offset = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Problem\EmptyEventAlteration {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\EmptyEventAlteration(name: $parts[0], definer: $parts[1], offset: $parts[2]);
        }, Generators::tuple(
            $name,
            $definer ?? Generators::constant(null),
            $offset ?? Generators::constant(0)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Problem\EmptyEventAlteration>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Routine\\Problem\\EmptyEventAlteration' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new EmptyEventAlterationGenerator(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 'e' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            definer: Generators::constant(null),
            offset: Generators::constant(13)
        )];
    }

}
