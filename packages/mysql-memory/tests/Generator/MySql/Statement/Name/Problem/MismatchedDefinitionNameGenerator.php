<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Name\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes MismatchedDefinitionName values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Problem\MismatchedDefinitionName>
 */
final class MismatchedDefinitionNameGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Name\Problem\MismatchedDefinitionName> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\ColumnName> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $target Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $target)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Name\Problem\MismatchedDefinitionName {
            return new \SqlSemantics\Platform\MySql\Statement\Name\Problem\MismatchedDefinitionName(name: $parts[0], target: $parts[1]);
        }, Generators::tuple(
            $name,
            $target
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Problem\MismatchedDefinitionName>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Name\\Problem\\MismatchedDefinitionName' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new MismatchedDefinitionNameGenerator(
            name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 'a' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $suffix): string => 'other' . $suffix, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $suffix): string => 'x' . $suffix, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    catalog: Generators::constant(null)
                ),
                dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            ),
            target: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
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
