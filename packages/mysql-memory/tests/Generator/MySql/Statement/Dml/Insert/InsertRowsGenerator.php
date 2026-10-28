<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Insert;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InsertRows values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows>
 */
final class InsertRowsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto> $into Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertedRow>> $rows Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\RowAlias|null>|null $alias Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Assignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment>>|null $onDuplicate Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesKeyword>|null $keyword Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $valuesSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $into, ErisGenerator $rows, ?ErisGenerator $alias = null, ?ErisGenerator $onDuplicate = null, ?ErisGenerator $keyword = null, ?ErisGenerator $valuesSource = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows(into: $parts[0], rows: $parts[1], alias: $parts[2], onDuplicate: $parts[3], keyword: $parts[4], valuesSource: $parts[5], statementInformation: $parts[6], inputSource: $parts[7]);
        }, \Tests\Generator\Operands::tuple(
            $into,
            $rows,
            $alias ?? Generators::constant(null),
            $onDuplicate ?? Generators::constant([]),
            $keyword ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesKeyword::Values),
            $valuesSource ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows>>
     */
    public static function forms(): iterable
    {
        yield 'InsertRows/52' => new InsertRowsGenerator(
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
            rows: \Tests\Generator\Operands::tuple(
                new InsertedRowGenerator(
                    values: Generators::constant([])
                )
            ),
            alias: Generators::constant(null),
            onDuplicate: Generators::constant([]),
            keyword: ValuesKeywordGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesKeyword::Value])
        );
        yield 'InsertRows/65' => new InsertRowsGenerator(
            into: new InsertIntoGenerator(
                replace: Generators::constant(true),
                priority: InsertPriorityGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertPriority::Delayed]),
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
            rows: \Tests\Generator\Operands::tuple(
                new InsertedRowGenerator(
                    values: Generators::constant([])
                )
            ),
            alias: Generators::constant(null),
            onDuplicate: Generators::constant([]),
            keyword: ValuesKeywordGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesKeyword::Value])
        );
        yield 'InsertRows/360' => new InsertRowsGenerator(
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
                columns: new ColumnListGenerator(
                    columns: Generators::constant([])
                ),
                hints: Generators::constant([]),
                intoWritten: Generators::constant(false)
            ),
            rows: \Tests\Generator\Operands::tuple(
                new InsertedRowGenerator(
                    values: Generators::constant([])
                )
            ),
            alias: Generators::constant(null),
            onDuplicate: Generators::constant([]),
            keyword: ValuesKeywordGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesKeyword::Value])
        );
        yield 'InsertRows/362' => new InsertRowsGenerator(
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
            rows: \Tests\Generator\Operands::tuple(
                new InsertedRowGenerator(
                    values: Generators::constant([])
                )
            ),
            alias: new RowAliasGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                columns: Generators::constant([])
            ),
            onDuplicate: Generators::constant([]),
            keyword: ValuesKeywordGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesKeyword::Value])
        );
    }

    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'InsertRows/additional/87' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new InsertRowsGenerator(
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
                hints: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\MySql\Statement\Hint\OptimizerHintGenerator([new \Tests\Generator\MySql\Statement\Hint\Form\BlockNameHintGenerator(
                        block: Generators::constant('b')
                    )])
                ),
                intoWritten: Generators::constant(true)
            ),
            rows: \Tests\Generator\Operands::tuple(
                new InsertedRowGenerator(
                    values: \Tests\Generator\Operands::tuple(
                        new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                        )])
                    )
                )
            ),
            alias: Generators::constant(null),
            onDuplicate: Generators::constant([]),
            keyword: ValuesKeywordGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesKeyword::Values])
        )];
    }

}
