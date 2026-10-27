<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RepeatedTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Problem\RepeatedTable>
 */
final class RepeatedTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Problem\RepeatedTable> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     */
    public function __construct(ErisGenerator $name)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Problem\RepeatedTable {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Problem\RepeatedTable(name: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $name
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Problem\RepeatedTable>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Alter\\Problem\\RepeatedTable' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RepeatedTableGenerator(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 't' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            )
        )];
    }

}
