<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes MissingColumn values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Reference\Column\MissingColumn>
 */
final class MissingColumnGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Reference\Column\MissingColumn> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName|null>|null $qualifier Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $qualifier = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Reference\Column\MissingColumn {
            return new \SqlSemantics\Statement\Reference\Column\MissingColumn(name: $parts[0], qualifier: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $qualifier ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Statement\Reference\Column\MissingColumn>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Reference\\Column\\MissingColumn' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new MissingColumnGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $suffix): string => 'a' . $suffix, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            qualifier: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
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
