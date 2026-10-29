<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Insert;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
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
     * Generates complete InsertRows values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }
            foreach (self::additionalForms() as [$grammar, $form]) {
                if ($grammar === $release) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No InsertRows forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows) {
                throw new LogicException('Publishing InsertRows must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto> $into Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertedRow>> $rows Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\RowAlias|null>|null $alias Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Assignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment>>|null $onDuplicate Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesKeyword>|null $keyword Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $valuesSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows>
     */
    public static function operands(ErisGenerator $into, ErisGenerator $rows, ?ErisGenerator $alias = null, ?ErisGenerator $onDuplicate = null, ?ErisGenerator $keyword = null, ?ErisGenerator $valuesSource = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows(into: $parts[0], rows: $parts[1], alias: $parts[2], onDuplicate: $parts[3], keyword: $parts[4], valuesSource: $parts[5], statementInformation: $parts[6], inputSource: $parts[7]);
        }, Generators::tuple($into, $rows, $alias ?? Generators::constant(null), $onDuplicate ?? Generators::constant([]), $keyword ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesKeyword::Values), $valuesSource ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows>>
     */
    public static function forms(): iterable
    {
        yield 'InsertRows/52' => InsertRowsGenerator::operands(
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
            rows: Generators::tuple(
                new InsertedRowGenerator(
                    values: Generators::constant([])
                )
            ),
            alias: Generators::constant(null),
            onDuplicate: Generators::constant([]),
            keyword: ValuesKeywordGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesKeyword::Value])
        );
        yield 'InsertRows/65' => InsertRowsGenerator::operands(
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
            rows: Generators::tuple(
                new InsertedRowGenerator(
                    values: Generators::constant([])
                )
            ),
            alias: Generators::constant(null),
            onDuplicate: Generators::constant([]),
            keyword: ValuesKeywordGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesKeyword::Value])
        );
        yield 'InsertRows/360' => InsertRowsGenerator::operands(
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
            rows: Generators::tuple(
                new InsertedRowGenerator(
                    values: Generators::constant([])
                )
            ),
            alias: Generators::constant(null),
            onDuplicate: Generators::constant([]),
            keyword: ValuesKeywordGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesKeyword::Value])
        );
        yield 'InsertRows/362' => InsertRowsGenerator::operands(
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
            rows: Generators::tuple(
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
        yield 'InsertRows/additional/87' => [\SqlSemantics\Contract\GrammarRelease::MySql847, InsertRowsGenerator::operands(
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
                hints: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\Hint\OptimizerHintGenerator([new \Tests\Generator\MySql\Statement\Hint\Form\BlockNameHintGenerator(
                        block: Generators::constant('b')
                    )])
                ),
                intoWritten: Generators::constant(true)
            ),
            rows: Generators::tuple(
                new InsertedRowGenerator(
                    values: Generators::tuple(
                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
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

    /**
     * Generates the table and its rows together, rebuilding bindings after shrinking.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Table\CreateTable, \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows}>
     */
    public static function withPrerequisites(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $tables = Generators::map(static function (\SqlSemantics\Platform\MySql\Statement\Table\CreateTable $table) use ($release): \SqlSemantics\Platform\MySql\Statement\Table\CreateTable {
            $context = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, $release->value, searchPath: new \SqlSemantics\Contract\SearchPath('pbt')))->context();
            $published = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $table);
            if (!$published instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable) {
                throw new LogicException('Publishing the prerequisite preserves its concrete type.');
            }
            return $published;
        }, \Tests\Generator\MySql\Statement\Table\CreateTableGenerator::integers());
        return Generators::map(static fn (array $parts): array => self::insertions($release, $parts[0], $parts[1], $parts[2]), Generators::tuple($tables, Generators::choose(1, 5), Generators::int()));
    }

    /**
     * Builds the insertion and its shared prerequisite from a generated declaration and row dimensions.
     *
     * @return array{\SqlSemantics\Platform\MySql\Statement\Table\CreateTable, \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows}
     * @throws LogicException When publication changes the concrete statement type.
     */
    public static function insertions(\SqlSemantics\Contract\GrammarRelease $release, \SqlSemantics\Platform\MySql\Statement\Table\CreateTable $table, int $count, int $base): array
    {
        $declaration = $table->declarations()[0];
        $rows = [];
        for ($row = 0; $row < $count; $row++) {
            $values = [];
            foreach ($declaration->columns as $position => $column) {
                $value = $base + $row * count($declaration->columns) + $position;
                $literal = new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral((string) abs($value));
                $values[] = $value < 0 ? new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Minus, $literal) : $literal;
            }
            $rows[] = new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow($values);
        }
        $context = new \SqlSemantics\Contract\AnalysisContext($table->profile(), [new \SqlSemantics\Statement\Identifier\Name('pbt')], $table->declarations(), session: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::defaults($release));
        $statement = new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows(new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto(false, null, false, new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget($table->name)), $rows);
        $insert = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $statement);
        if (!$insert instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows) {
            throw new LogicException('Publishing an insertion preserves its concrete type.');
        }
        return [$table, $insert];
    }
}
