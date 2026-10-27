<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem\Insert;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedInsertQuery values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertQuery>
 */
final class RefusedInsertQueryGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertQuery> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertQuery> $write Generated constructor operand
     */
    public function __construct(ErisGenerator $write)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertQuery {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertQuery(write: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $write
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertQuery>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml\\Problem\\Insert\\RefusedInsertQuery' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedInsertQueryGenerator(
            write: new \Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredInsertQueryGenerator(
                into: new \Tests\Generator\MySql\Statement\Dml\Insert\InsertIntoGenerator(
                    replace: Generators::constant(true),
                    priority: Generators::constant(null),
                    ignore: Generators::constant(false),
                    table: new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(
                        name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $suffix): string => 't' . $suffix, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            schema: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $suffix): string => 'absent' . $suffix, Generators::choose(0, 8)),
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
                    intoWritten: Generators::constant(false)
                ),
                source: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\With\DeferredBodyGenerator(
                    selection: new \Tests\Generator\MySql\Statement\Routine\Program\DeferredSelectionGenerator(
                        items: \Tests\Generator\Operands::tuple(
                            new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                                expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                )]),
                                alias: Generators::constant(null),
                                layout: Generators::constant(null),
                                mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                spellingProfile: Generators::constant(null)
                            )])
                        ),
                        from: Generators::constant(null),
                        where: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                            elements: \Tests\Generator\Operands::tuple(
                                new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                )]),
                                new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                )])
                            ),
                            keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                        )]),
                        options: Generators::constant([]),
                        groupBy: Generators::constant(null),
                        having: Generators::constant(null),
                        orderBy: Generators::constant([]),
                        limit: Generators::constant(null),
                        windows: Generators::constant([]),
                        procedure: Generators::constant(null),
                        qualify: Generators::constant(null),
                        into: new \Tests\Generator\MySql\Statement\Query\Into\IntoDumpfileGenerator(
                            file: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                                value: Generators::constant('text'),
                                radix: Generators::constant(null)
                            )
                        )
                    )
                )]),
                onDuplicate: Generators::constant([])
            )
        )];
    }

}
