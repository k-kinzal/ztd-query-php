<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Table;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes MissingTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Reference\Table\MissingTable>
 */
final class MissingTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Reference\Table\MissingTable> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     */
    public function __construct(ErisGenerator $name)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Reference\Table\MissingTable {
            return new \SqlSemantics\Statement\Reference\Table\MissingTable(name: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $name
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Statement\Reference\Table\MissingTable>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Reference\\Table\\MissingTable' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new MissingTableGenerator(
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
