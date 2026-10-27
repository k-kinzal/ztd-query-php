<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem\Insert;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedInsertSet values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertSet>
 */
final class RefusedInsertSetGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertSet> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertSet> $write Generated constructor operand
     */
    public function __construct(ErisGenerator $write)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertSet {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertSet(write: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $write
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertSet>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml\\Problem\\Insert\\RefusedInsertSet' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedInsertSetGenerator(
            write: new \Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredInsertSetGenerator(
                into: new \Tests\Generator\MySql\Statement\Dml\Insert\InsertIntoGenerator(
                    replace: Generators::constant(false),
                    priority: Generators::constant(null),
                    ignore: Generators::constant(false),
                    table: new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(
                        name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $suffix): string => 't' . $suffix, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            schema: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $suffix): string => 'd' . $suffix, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            catalog: Generators::constant(null)
                        ),
                        alias: Generators::constant(null),
                        partitions: Generators::constant([]),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    ),
                    columns: Generators::constant(null),
                    hints: Generators::constant([]),
                    intoWritten: Generators::constant(true)
                ),
                assignments: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\MySql\Statement\Dml\AssignmentGenerator(
                        column: new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $suffix): string => 'a' . $suffix, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            qualifier: Generators::constant(null),
                            dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        ),
                        value: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                        )])
                    ),
                    new \Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredColumnAssignmentGenerator(
                        column: new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $suffix): string => 'b' . $suffix, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            qualifier: Generators::constant(null),
                            dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
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
                            keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                        )])
                    )
                ),
                alias: Generators::constant(null),
                onDuplicate: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\MySql\Statement\Dml\AssignmentGenerator(
                        column: new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $suffix): string => 'c' . $suffix, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            qualifier: Generators::constant(null),
                            dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        ),
                        value: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                        )])
                    )
                )
            )
        )];
    }

}
