<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem\Update;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ConflictingUpdate values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate>
 */
final class ConflictingUpdateGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\WithClause|null> $with Generated constructor operand
     * @param ErisGenerator<bool> $lowPriority Generated constructor operand
     * @param ErisGenerator<bool> $ignore Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Relation\TableSource>> $tables Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Assignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment>> $assignments Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand|null>|null $where Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\OrderItem|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem>>|null $orderBy Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Limit|null>|null $limit Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint>>|null $hints Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $with, ErisGenerator $lowPriority, ErisGenerator $ignore, ErisGenerator $tables, ErisGenerator $assignments, ?ErisGenerator $where = null, ?ErisGenerator $orderBy = null, ?ErisGenerator $limit = null, ?ErisGenerator $hints = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate(with: $parts[0], lowPriority: $parts[1], ignore: $parts[2], tables: $parts[3], assignments: $parts[4], where: $parts[5], orderBy: $parts[6], limit: $parts[7], hints: $parts[8], inputSource: $parts[9]);
        }, \Tests\Generator\Operands::tuple(
            $with,
            $lowPriority,
            $ignore,
            $tables,
            $assignments,
            $where ?? Generators::constant(null),
            $orderBy ?? Generators::constant([]),
            $limit ?? Generators::constant(null),
            $hints ?? Generators::constant([]),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml\\Problem\\Update\\ConflictingUpdate' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new ConflictingUpdateGenerator(
            with: new \Tests\Generator\MySql\Statement\Query\With\WithGenerator(
                recursive: Generators::constant(false),
                tables: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\MySql\Statement\Query\With\CommonTableExpressionGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('c'),
                            quote: Generators::constant(null)
                        ),
                        columns: Generators::constant([]),
                        query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\With\DeferredBodyGenerator(
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
                                where: Generators::constant(null),
                                options: Generators::constant([]),
                                groupBy: Generators::constant(null),
                                having: Generators::constant(null),
                                orderBy: Generators::constant([]),
                                limit: Generators::constant(null),
                                windows: Generators::constant([]),
                                procedure: Generators::constant(null),
                                qualify: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
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
                                into: Generators::constant(null)
                            )
                        )])
                    )
                )
            ),
            lowPriority: Generators::constant(false),
            ignore: Generators::constant(false),
            tables: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Relation\TableSourceGenerator([new \Tests\Generator\MySql\Statement\Relation\TableReferenceGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('t'),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    ),
                    alias: Generators::constant(null),
                    partitions: Generators::constant([]),
                    indexHints: Generators::constant([]),
                    sample: Generators::constant(null),
                    mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                    dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                )]),
                new \Tests\Generator\MySql\Statement\Relation\TableSourceGenerator([new \Tests\Generator\MySql\Statement\Relation\TableReferenceGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('t'),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    ),
                    alias: Generators::constant(null),
                    partitions: Generators::constant([]),
                    indexHints: Generators::constant([]),
                    sample: Generators::constant(null),
                    mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                    dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                )])
            ),
            assignments: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Dml\AssignmentGenerator(
                    column: new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('a'),
                            quote: Generators::constant(null)
                        ),
                        qualifier: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    value: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                    )])
                )
            ),
            where: Generators::constant(null),
            orderBy: Generators::constant([]),
            limit: Generators::constant(null),
            hints: Generators::constant([])
        )];
    }

}
