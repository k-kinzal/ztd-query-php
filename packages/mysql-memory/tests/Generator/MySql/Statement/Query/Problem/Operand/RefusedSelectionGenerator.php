<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem\Operand;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedSelection values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSelection>
 */
final class RefusedSelectionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSelection> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection> $selection Generated constructor operand
     */
    public function __construct(ErisGenerator $selection)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSelection {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSelection(selection: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $selection
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSelection>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Query\\Problem\\Operand\\RefusedSelection' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedSelectionGenerator(
            selection: new \Tests\Generator\MySql\Statement\Routine\Program\DeferredSelectionGenerator(
                items: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Routine\Program\DeferredProjectionGenerator(
                        expression: new \Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator([new \Tests\Generator\MySql\Statement\Routine\Program\Expression\Operator\DeferredAssignmentGenerator(
                            target: new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::map(static fn (int $suffix): string => 'a' . $suffix, Generators::choose(0, 8)),
                                    quote: Generators::constant(null)
                                )
                            ),
                            value: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                                elements: \Tests\Generator\Operands::tuple(
                                    new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )]),
                                    new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )])
                                ),
                                keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )]),
                        alias: Generators::constant(null),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    )])
                ),
                from: Generators::constant(null),
                where: Generators::constant(null),
                options: Generators::constant([]),
                groupBy: Generators::constant(null),
                having: Generators::constant(null),
                orderBy: Generators::constant([]),
                limit: Generators::constant(null),
                windows: Generators::constant([]),
                procedure: Generators::constant(null),
                qualify: Generators::constant(null),
                into: Generators::constant(null)
            )
        )];
    }

}
