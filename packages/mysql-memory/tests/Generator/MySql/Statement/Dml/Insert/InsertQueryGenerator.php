<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Insert;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InsertQuery values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery>
 */
final class InsertQueryGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto> $into Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Query> $source Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Assignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment>>|null $onDuplicate Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $into, ErisGenerator $source, ?ErisGenerator $onDuplicate = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery(into: $parts[0], source: $parts[1], onDuplicate: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $into,
            $source,
            $onDuplicate ?? Generators::constant([]),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery>>
     */
    public static function forms(): iterable
    {
        yield 'InsertQuery/359' => new InsertQueryGenerator(
            into: new InsertIntoGenerator(
                replace: Generators::constant(false),
                priority: Generators::constant(null),
                ignore: Generators::constant(false),
                table: new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
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
            source: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ExplicitTableGenerator(
                table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                )
            )]),
            onDuplicate: Generators::constant([])
        );
    }

    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'InsertQuery/additional/10' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new InsertQueryGenerator(
            into: new InsertIntoGenerator(
                replace: Generators::constant(false),
                priority: Generators::constant(null),
                ignore: Generators::constant(false),
                table: new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(
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
                    mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                ),
                columns: Generators::constant(null),
                hints: Generators::constant([]),
                intoWritten: Generators::constant(true)
            ),
            source: new \Tests\Generator\Semantics\Statement\QueryGenerator([new ValuesClausesGenerator(
                with: Generators::constant(null),
                body: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ValuesQueryGenerator(
                    rows: \Tests\Generator\Operands::tuple(
                        new \Tests\Generator\MySql\Statement\Query\ValueRowGenerator(
                            values: \Tests\Generator\Operands::tuple(
                                \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                )])
                            )
                        )
                    )
                )]),
                orderBy: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredOrderItemGenerator(
                        expression: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                            elements: \Tests\Generator\Operands::tuple(
                                \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                )]),
                                \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                )])
                            ),
                            keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                        )]),
                        direction: Generators::constant(null)
                    )
                ),
                limit: new \Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator(
                    count: new \Tests\Generator\MySql\Statement\Query\Clause\ProgramVariableGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('absent'),
                            quote: Generators::constant(null)
                        )
                    ),
                    offset: Generators::constant(null),
                    spelling: Generators::constant(null)
                )
            )]),
            onDuplicate: Generators::constant([])
        )];
        yield 'InsertQuery/additional/11' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new InsertQueryGenerator(
            into: new InsertIntoGenerator(
                replace: Generators::constant(false),
                priority: Generators::constant(null),
                ignore: Generators::constant(false),
                table: new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(
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
                    mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                ),
                columns: Generators::constant(null),
                hints: Generators::constant([]),
                intoWritten: Generators::constant(true)
            ),
            source: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\QueryStatementGenerator(
                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new ValuesClausesGenerator(
                    with: new \Tests\Generator\MySql\Statement\Query\With\WithGenerator(
                        recursive: Generators::constant(false),
                        tables: \Tests\Generator\Operands::tuple(
                            new \Tests\Generator\MySql\Statement\Query\With\CommonTableExpressionGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('x'),
                                    quote: Generators::constant(null)
                                ),
                                columns: Generators::constant([]),
                                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
                                    options: Generators::constant([]),
                                    items: \Tests\Generator\Operands::tuple(
                                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                                            expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                                    value: Generators::constant('absent'),
                                                    quote: Generators::constant(null)
                                                ),
                                                qualifier: Generators::constant(null),
                                                dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                            )]),
                                            alias: Generators::constant(null),
                                            layout: Generators::constant(null),
                                            mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                            spellingProfile: Generators::constant(null)
                                        )])
                                    ),
                                    from: Generators::constant(null),
                                    where: Generators::constant(null),
                                    groupBy: Generators::constant(null),
                                    having: Generators::constant(null),
                                    windows: Generators::constant([]),
                                    qualify: Generators::constant(null),
                                    orderBy: Generators::constant([]),
                                    limit: Generators::constant(null),
                                    procedure: Generators::constant(null),
                                    locking: Generators::constant([]),
                                    into: Generators::constant(null),
                                    intoPosition: Generators::constant(null),
                                    late: Generators::constant(null),
                                    hints: Generators::constant([])
                                )])
                            )
                        )
                    ),
                    body: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ParenthesizedQueryGenerator(
                        query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ValuesQueryGenerator(
                            rows: \Tests\Generator\Operands::tuple(
                                new \Tests\Generator\MySql\Statement\Query\ValueRowGenerator(
                                    values: \Tests\Generator\Operands::tuple(
                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )])
                                    )
                                )
                            )
                        )])
                    )]),
                    orderBy: \Tests\Generator\Operands::tuple(
                        new \Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredOrderItemGenerator(
                            expression: new \Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator([new \Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DormantCallGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('ABS'),
                                    quote: Generators::constant(null)
                                ),
                                arguments: Generators::constant([])
                            )]),
                            direction: Generators::constant(null)
                        )
                    ),
                    limit: new \Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator(
                        count: new \Tests\Generator\MySql\Statement\Query\Clause\ProgramVariableGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('absent'),
                                quote: Generators::constant(null)
                            )
                        ),
                        offset: Generators::constant(null),
                        spelling: Generators::constant(null)
                    )
                )]),
                locking: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\MySql\Statement\Query\Locking\LockingClauseGenerator(
                        strength: \Tests\Generator\MySql\Statement\Query\Locking\LockStrengthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength::Update]),
                        tables: \Tests\Generator\Operands::tuple(
                            new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('missing'),
                                    quote: Generators::constant(null)
                                ),
                                schema: Generators::constant(null),
                                catalog: Generators::constant(null)
                            )
                        ),
                        action: Generators::constant(null),
                        wildcards: \Tests\Generator\Operands::tuple(
                            \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        )
                    )
                ),
                into: Generators::constant(null),
                intoPosition: Generators::constant(null)
            )]),
            onDuplicate: Generators::constant([])
        )];
    }

}
