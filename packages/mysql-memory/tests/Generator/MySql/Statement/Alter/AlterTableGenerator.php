<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Statement as MySql;
use SqlSemantics\Statement\AnalyzedStatement;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Composition;

/**
 * Composes AlterTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\AlterTable>
 */
final class AlterTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\AlterTable> */
    use Composition;

    /**
     * @param ErisGenerator<QualifiedName> $table Generated constructor operand
     * @param ErisGenerator<list<MySql\Alter\AlterCommand>> $commands Generated constructor operand
     * @param ErisGenerator<bool>|null $ignore Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     * @param ErisGenerator<MySql\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $table, ErisGenerator $commands, ?ErisGenerator $ignore = null, ?ErisGenerator $relationInformation = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): MySql\Alter\AlterTable {
            return new MySql\Alter\AlterTable(table: $parts[0], commands: $parts[1], ignore: $parts[2], relationInformation: $parts[3], statementInformation: $parts[4], inputSource: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $table,
            $commands,
            $ignore ?? Generators::constant(false),
            $relationInformation ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Generates a table, its rows and a dependent index alteration before execution.
     * Shrinking the names, engine, temporary scope or rows rebuilds all dependent values.
     *
     * @return ErisGenerator<array{AnalyzedStatement, AnalyzedStatement, AnalyzedStatement, int}>
     */
    public static function indexSequences(GrammarRelease $release): ErisGenerator
    {
        $values = new \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator(MySql\Type\Resolved\Collation::known('utf8mb4_unicode_ci'), Generators::map(static fn (int $value): string => (string) $value, Generators::choose(-10000, 10000)));
        $rows = \Tests\Generator\Operands::bind(Generators::choose(1, 5), static fn (int $count): ErisGenerator => \Tests\Generator\Operands::vector($count, new \Tests\Generator\MySql\Statement\Dml\Insert\InsertedRowGenerator(\Tests\Generator\Operands::vector(2, $values))));
        $operands = \Tests\Generator\Operands::tuple(
            new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(),
            new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(),
            Generators::elements(['InnoDB', 'MyISAM', 'MEMORY']),
            Generators::choose(0, 1),
            Generators::elements($release === GrammarRelease::MySql5651 ? [0, 1, 3, 4, 5] : [0, 1, 2, 3, 4, 5]),
            new \Tests\Generator\MySql\Statement\Table\IndexWordGenerator(),
            $rows,
        );
        return Generators::map(static function (array $parts) use ($release): array {
            [$name, $column, $engine, $temporary, $action, $word, $rows] = $parts;
            $table = new QualifiedName($name);
            $second = new Name($column->value . '_b', $column->quote);
            $firstIndex = new MySql\Name\ColumnName(new Name('i'));
            $secondIndex = new MySql\Name\ColumnName(new Name('j'));
            $index = new MySql\Table\Key\IndexDefinition(MySql\Table\Key\IndexKind::Index, [new MySql\Table\Key\ColumnPart($column)], $firstIndex, keyword: $word);
            $elements = [];
            foreach ([$column, $second] as $field) {
                $elements[] = new MySql\Table\Column\ColumnDefinition(new MySql\Name\ColumnName($field), new MySql\Table\Column\OrdinaryColumn(new MySql\Type\Integral(MySql\Type\Kind\IntegralKind::Int)));
            }
            $elements[] = $index;
            $create = new MySql\Table\CreateTable($table, $elements, [new MySql\Table\Option\EngineOption(new Name($engine))], temporaryWords: $temporary);
            $add = new MySql\Alter\Command\AddConstraint(new MySql\Table\Key\IndexDefinition(MySql\Table\Key\IndexKind::Index, [new MySql\Table\Key\ColumnPart(in_array($action, [0, 5], true) ? $second : $column)], in_array($action, [0, 4], true) ? $secondIndex : $firstIndex, keyword: $word));
            $drop = new MySql\Alter\Command\DropElement(MySql\Alter\Command\ElementKind::Index, new MySql\Name\ColumnName($firstIndex->column), keyword: $word);
            $commands = match ($action) {
                0 => [$add],
                1 => [$drop],
                2 => [new MySql\Alter\Command\RenameElement(MySql\Alter\Command\ElementKind::Index, $firstIndex, $secondIndex, keyword: $word)],
                default => [$drop, $add],
            };
            $context = new AnalysisContext(new LanguageProfile($release), [new Name('pbt')], session: new MySql\Type\Resolved\Settings(MySql\Type\Resolved\Collation::known('utf8mb4_unicode_ci')));
            $publication = new Publication();
            $definition = $publication->publish($context, $create);
            $context = new AnalysisContext($context->profile, [new Name('pbt')], $definition->declarations(), session: $context->session);
            $insert = new MySql\Dml\Insert\InsertRows(new MySql\Dml\Insert\InsertInto(false, null, false, new MySql\Dml\WriteTarget($table)), $rows);
            $alter = new MySql\Alter\AlterTable($table, $commands);
            $copies = $temporary > 0 || ($engine !== 'InnoDB' && in_array($action, [0, 1, 4, 5], true));
            return [$definition, $publication->publish($context, $insert), $publication->publish($context, $alter), $copies ? count($rows) : 0];
        }, $operands);
    }

    /**
     * Composes functional-index and virtual-column changes with the table and rows they need.
     *
     * @return ErisGenerator<array{AnalyzedStatement, AnalyzedStatement, AnalyzedStatement, int}>
     */
    public static function functionalSequences(GrammarRelease $release): ErisGenerator
    {
        $values = new \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator(MySql\Type\Resolved\Collation::known('utf8mb4_unicode_ci'), Generators::map(static fn (int $value): string => (string) $value, Generators::choose(-10000, 10000)));
        $rows = \Tests\Generator\Operands::bind(Generators::choose(1, 5), static fn (int $count): ErisGenerator => \Tests\Generator\Operands::vector($count, new \Tests\Generator\MySql\Statement\Dml\Insert\InsertedRowGenerator(\Tests\Generator\Operands::vector(1, $values))));
        return Generators::map(static function (array $parts) use ($release): array {
            [$name, $column, $rows, $action] = $parts;
            $table = new QualifiedName($name);
            $expression = static fn (string $amount): MySql\Expression\Operator\Arithmetic => new MySql\Expression\Operator\Arithmetic(MySql\Expression\Operator\ArithmeticOperator::Plus, new MySql\Name\ColumnUse($column), new MySql\Literal\NumberLiteral($amount));
            $index = new MySql\Table\Key\IndexDefinition(MySql\Table\Key\IndexKind::Index, [new MySql\Table\Key\ExpressionPart($expression('1'))], new MySql\Name\ColumnName(new Name('i')));
            $field = new MySql\Table\Column\ColumnDefinition(new MySql\Name\ColumnName($column), new MySql\Table\Column\OrdinaryColumn(new MySql\Type\Integral(MySql\Type\Kind\IntegralKind::Int)));
            $create = new MySql\Table\CreateTable($table, [$field, $index]);
            $virtual = new MySql\Table\Column\ColumnDefinition(new MySql\Name\ColumnName(new Name('generated_value')), new MySql\Table\Column\GeneratedColumn(new MySql\Type\Integral(MySql\Type\Kind\IntegralKind::Int), $expression('2')));
            $add = new MySql\Alter\Command\AddConstraint(new MySql\Table\Key\IndexDefinition(MySql\Table\Key\IndexKind::Index, [new MySql\Table\Key\ExpressionPart($expression($action === 0 ? '2' : '1'))], new MySql\Name\ColumnName(new Name($action === 0 ? 'j' : 'i'))));
            $drop = new MySql\Alter\Command\DropElement(MySql\Alter\Command\ElementKind::Index, new MySql\Name\ColumnName(new Name('i')));
            $commands = match ($action) {
                0 => [$add],
                1 => [$drop],
                2 => [$drop, $add],
                3 => [new MySql\Alter\Column\AddColumns([$virtual])],
                default => [new MySql\Alter\Column\AddColumns([$virtual]), new MySql\Alter\Command\AddConstraint(new MySql\Table\Key\IndexDefinition(MySql\Table\Key\IndexKind::Index, [new MySql\Table\Key\ColumnPart(new Name('generated_value'))], new MySql\Name\ColumnName(new Name('j'))))],
            };
            $context = new AnalysisContext(new LanguageProfile($release), [new Name('pbt')], session: new MySql\Type\Resolved\Settings(MySql\Type\Resolved\Collation::known('utf8mb4_unicode_ci')));
            $publication = new Publication();
            $definition = $publication->publish($context, $create);
            $context = new AnalysisContext($context->profile, [new Name('pbt')], $definition->declarations(), session: $context->session);
            $insert = new MySql\Dml\Insert\InsertRows(new MySql\Dml\Insert\InsertInto(false, null, false, new MySql\Dml\WriteTarget($table)), $rows);
            return [$definition, $publication->publish($context, $insert), $publication->publish($context, new MySql\Alter\AlterTable($table, $commands)), $action >= 2 ? count($rows) : 0];
        }, \Tests\Generator\Operands::tuple(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), $rows, Generators::choose(0, 4)));
    }

    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<MySql\Alter\AlterTable>>
     */
    public static function forms(): iterable
    {
        yield 'AlterTable/11' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::constant([]),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/184' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Partition\PartitionByGenerator(
                    partitioning: new \Tests\Generator\MySql\Statement\Partition\PartitionClauseGenerator(
                        method: new \Tests\Generator\MySql\Statement\Partition\Method\ColumnsMethodGenerator(
                            kind: \Tests\Generator\MySql\Statement\Partition\Method\PartitionKindGenerator::selecting([MySql\Partition\Method\PartitionKind::Range]),
                            columns: \Tests\Generator\Operands::tuple(
                                \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                    quote: Generators::constant(null)
                                )
                            )
                        ),
                        partitions: Generators::constant(null),
                        subpartitioning: Generators::constant(null),
                        definitions: Generators::constant([])
                    )
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/185' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Partition\PartitionByGenerator(
                    partitioning: new \Tests\Generator\MySql\Statement\Partition\PartitionClauseGenerator(
                        method: new \Tests\Generator\MySql\Statement\Partition\Method\KeyMethodGenerator(
                            linear: Generators::constant(false),
                            algorithm: Generators::constant(null),
                            columns: Generators::constant([])
                        ),
                        partitions: Generators::constant(null),
                        subpartitioning: new \Tests\Generator\MySql\Statement\Partition\SubpartitioningGenerator(
                            method: new \Tests\Generator\MySql\Statement\Partition\Method\KeyMethodGenerator(
                                linear: Generators::constant(false),
                                algorithm: Generators::constant(null),
                                columns: \Tests\Generator\Operands::tuple(
                                    \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                        quote: Generators::constant(null)
                                    )
                                )
                            ),
                            count: Generators::constant(null)
                        ),
                        definitions: Generators::constant([])
                    )
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/186' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Partition\PartitionByGenerator(
                    partitioning: new \Tests\Generator\MySql\Statement\Partition\PartitionClauseGenerator(
                        method: new \Tests\Generator\MySql\Statement\Partition\Method\KeyMethodGenerator(
                            linear: Generators::constant(false),
                            algorithm: Generators::constant(null),
                            columns: Generators::constant([])
                        ),
                        partitions: Generators::constant(null),
                        subpartitioning: Generators::constant(null),
                        definitions: \Tests\Generator\Operands::tuple(
                            new \Tests\Generator\MySql\Statement\Partition\PartitionDefinitionGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                    quote: Generators::constant(null)
                                ),
                                values: Generators::constant(null),
                                options: Generators::constant([]),
                                subpartitions: Generators::constant([])
                            )
                        )
                    )
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/187' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Partition\AddPartitionGenerator(
                    binlog: Generators::constant(null),
                    definitions: \Tests\Generator\Operands::tuple(
                        new \Tests\Generator\MySql\Statement\Partition\PartitionDefinitionGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            values: Generators::constant(null),
                            options: Generators::constant([]),
                            subpartitions: Generators::constant([])
                        ),
                        new \Tests\Generator\MySql\Statement\Partition\PartitionDefinitionGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            values: Generators::constant(null),
                            options: Generators::constant([]),
                            subpartitions: Generators::constant([])
                        )
                    ),
                    count: Generators::constant(null)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/188' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Partition\AddPartitionGenerator(
                    binlog: Generators::constant(null),
                    definitions: \Tests\Generator\Operands::tuple(
                        new \Tests\Generator\MySql\Statement\Partition\PartitionDefinitionGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            values: new \Tests\Generator\MySql\Statement\Partition\Bound\LessThanGenerator(
                                row: Generators::constant(null)
                            ),
                            options: Generators::constant([]),
                            subpartitions: Generators::constant([])
                        )
                    ),
                    count: Generators::constant(null)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/189' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Partition\AddPartitionGenerator(
                    binlog: Generators::constant(null),
                    definitions: \Tests\Generator\Operands::tuple(
                        new \Tests\Generator\MySql\Statement\Partition\PartitionDefinitionGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            values: new \Tests\Generator\MySql\Statement\Partition\Bound\ValuesInGenerator(
                                row: new \Tests\Generator\MySql\Statement\Partition\Bound\PartitionRowGenerator(
                                    items: \Tests\Generator\Operands::tuple(
                                        \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                        )])
                                    )
                                )
                            ),
                            options: Generators::constant([]),
                            subpartitions: Generators::constant([])
                        )
                    ),
                    count: Generators::constant(null)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/190' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Partition\AddPartitionGenerator(
                    binlog: Generators::constant(null),
                    definitions: \Tests\Generator\Operands::tuple(
                        new \Tests\Generator\MySql\Statement\Partition\PartitionDefinitionGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            values: new \Tests\Generator\MySql\Statement\Partition\Bound\LessThanGenerator(
                                row: new \Tests\Generator\MySql\Statement\Partition\Bound\PartitionRowGenerator(
                                    items: \Tests\Generator\Operands::tuple(
                                        new \Tests\Generator\MySql\Statement\Partition\Bound\PartitionMaximumGenerator()
                                    )
                                )
                            ),
                            options: Generators::constant([]),
                            subpartitions: Generators::constant([])
                        )
                    ),
                    count: Generators::constant(null)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/191' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Partition\AddPartitionGenerator(
                    binlog: Generators::constant(null),
                    definitions: \Tests\Generator\Operands::tuple(
                        new \Tests\Generator\MySql\Statement\Partition\PartitionDefinitionGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            values: new \Tests\Generator\MySql\Statement\Partition\Bound\ValuesInRowsGenerator(
                                rows: \Tests\Generator\Operands::tuple(
                                    new \Tests\Generator\MySql\Statement\Partition\Bound\PartitionRowGenerator(
                                        items: \Tests\Generator\Operands::tuple(
                                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                            )]),
                                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                            )])
                                        )
                                    )
                                )
                            ),
                            options: Generators::constant([]),
                            subpartitions: Generators::constant([])
                        )
                    ),
                    count: Generators::constant(null)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/192' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Partition\AddPartitionGenerator(
                    binlog: Generators::constant(null),
                    definitions: \Tests\Generator\Operands::tuple(
                        new \Tests\Generator\MySql\Statement\Partition\PartitionDefinitionGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            values: Generators::constant(null),
                            options: Generators::constant([]),
                            subpartitions: \Tests\Generator\Operands::tuple(
                                new \Tests\Generator\MySql\Statement\Partition\SubpartitionDefinitionGenerator(
                                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                        quote: Generators::constant(null)
                                    ),
                                    options: Generators::constant([])
                                )
                            )
                        )
                    ),
                    count: Generators::constant(null)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/193' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Partition\AddPartitionGenerator(
                    binlog: Generators::constant(null),
                    definitions: \Tests\Generator\Operands::tuple(
                        new \Tests\Generator\MySql\Statement\Partition\PartitionDefinitionGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            values: Generators::constant(null),
                            options: \Tests\Generator\Operands::tuple(
                                new \Tests\Generator\MySql\Statement\Partition\PartitionOptionGenerator(
                                    kind: \Tests\Generator\MySql\Statement\Partition\PartitionOptionKindGenerator::selecting([MySql\Partition\PartitionOptionKind::NodeGroup]),
                                    name: Generators::constant(null),
                                    number: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                                        text: Generators::constant('1'),
                                        hexadecimal: Generators::constant(false)
                                    ),
                                    text: Generators::constant(null)
                                )
                            ),
                            subpartitions: Generators::constant([])
                        )
                    ),
                    count: Generators::constant(null)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/196' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Command\SetTableOptionsGenerator(
                    options: \Tests\Generator\Operands::tuple(
                        new \Tests\Generator\MySql\Statement\Table\Option\StorageOptionGenerator(
                            medium: \Tests\Generator\MySql\Statement\Table\Column\Kind\StorageMediumGenerator::selecting([MySql\Table\Column\Kind\StorageMedium::Disk])
                        )
                    )
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/214' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Command\ConstraintEnforcementGenerator(
                    kind: Command\ElementKindGenerator::selecting([MySql\Alter\Command\ElementKind::Check]),
                    constraint: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    enforced: Generators::constant(true)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/244' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Column\ColumnVisibilityGenerator(
                    column: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                    ),
                    visible: Generators::constant(false),
                    columnWritten: Generators::constant(false)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/253' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Modifier\ValidationOptionGenerator(
                    validate: Generators::constant(true)
                ),
                new Partition\TablespaceCommandGenerator(
                    import: Generators::constant(false),
                    partitions: Generators::constant(null)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/254' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Partition\RemovePartitioningGenerator()
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/255' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Modifier\ValidationOptionGenerator(
                    validate: Generators::constant(true)
                ),
                new Command\ForceGenerator()
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/256' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Partition\DropPartitionGenerator(
                    partitions: new \Tests\Generator\MySql\Statement\Partition\NamedPartitionsGenerator(
                        names: \Tests\Generator\Operands::tuple(
                            \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            )
                        )
                    )
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/257' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Partition\MaintainPartitionsGenerator(
                    kind: Partition\MaintenanceKindGenerator::selecting([MySql\Alter\Partition\MaintenanceKind::Rebuild]),
                    binlog: Generators::constant(null),
                    partitions: new \Tests\Generator\MySql\Statement\Partition\AllPartitionsGenerator(),
                    options: Generators::constant([]),
                    ignoredBinlog: Generators::constant(null)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/258' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Partition\CoalescePartitionGenerator(
                    binlog: Generators::constant(null),
                    count: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                        text: Generators::constant('1'),
                        hexadecimal: Generators::constant(false)
                    )
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/259' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Partition\ReorganizePartitionGenerator(
                    binlog: Generators::constant(null),
                    partitions: Generators::constant(null),
                    definitions: Generators::constant([])
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/260' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Partition\ExchangePartitionGenerator(
                    partition: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    ),
                    validation: Generators::constant(null)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/261' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Partition\SecondaryLoadGenerator(
                    load: Generators::constant(true),
                    partitions: Generators::constant([])
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/262' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Column\AddColumnGenerator(
                    column: new \Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator(
                        name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                            column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            table: Generators::constant(null),
                            dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                        ),
                        specification: new \Tests\Generator\MySql\Statement\Table\Column\OrdinaryColumnGenerator(
                            type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                                kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([MySql\Type\Kind\ElementaryKind::Bit]),
                                length: Generators::constant(null)
                            )]),
                            attributes: Generators::constant([]),
                            references: Generators::constant(null)
                        )
                    ),
                    position: Generators::constant(null),
                    columnWritten: Generators::constant(false)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/263' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Column\AddColumnsGenerator(
                    elements: \Tests\Generator\Operands::tuple(
                        new \Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator(
                            name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                                column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                    quote: Generators::constant(null)
                                ),
                                table: Generators::constant(null),
                                dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                            ),
                            specification: new \Tests\Generator\MySql\Statement\Table\Column\OrdinaryColumnGenerator(
                                type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                                    kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([MySql\Type\Kind\ElementaryKind::Bit]),
                                    length: Generators::constant(null)
                                )]),
                                attributes: Generators::constant([]),
                                references: Generators::constant(null)
                            )
                        )
                    ),
                    columnWritten: Generators::constant(false)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/264' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Command\AddConstraintGenerator(
                    element: new \Tests\Generator\MySql\Statement\Table\Key\IndexDefinitionGenerator(
                        kind: \Tests\Generator\MySql\Statement\Table\Key\IndexKindGenerator::selecting([MySql\Table\Key\IndexKind::FullText]),
                        parts: \Tests\Generator\Operands::tuple(
                            new \Tests\Generator\MySql\Statement\Table\Key\ColumnPartGenerator(
                                column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                    quote: Generators::constant(null)
                                ),
                                length: Generators::constant(null),
                                direction: Generators::constant(null)
                            )
                        ),
                        name: Generators::constant(null),
                        algorithm: Generators::constant(null),
                        options: Generators::constant([]),
                        constraint: Generators::constant(null),
                        keyword: Generators::constant(null)
                    )
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/265' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Column\ChangeColumnGenerator(
                    column: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                    ),
                    definition: new \Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator(
                        name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                            column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            table: Generators::constant(null),
                            dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                        ),
                        specification: new \Tests\Generator\MySql\Statement\Table\Column\OrdinaryColumnGenerator(
                            type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                                kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([MySql\Type\Kind\ElementaryKind::Bit]),
                                length: Generators::constant(null)
                            )]),
                            attributes: Generators::constant([]),
                            references: Generators::constant(null)
                        )
                    ),
                    position: Generators::constant(null),
                    columnWritten: Generators::constant(false)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/266' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Command\DropElementGenerator(
                    kind: Command\ElementKindGenerator::selecting([MySql\Alter\Command\ElementKind::Column]),
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                    ),
                    behavior: Generators::constant(null),
                    columnWritten: Generators::constant(false)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/267' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Command\ToggleKeysGenerator(
                    enable: Generators::constant(false)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/268' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Column\DefaultSettingGenerator(
                    column: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                    ),
                    default: new \Tests\Generator\MySql\Statement\Table\Column\DefaultLiteralGenerator(
                        value: new \Tests\Generator\MySql\Statement\LiteralGenerator([new \Tests\Generator\MySql\Statement\Literal\NullLiteralGenerator()])
                    ),
                    columnWritten: Generators::constant(false)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/269' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Command\IndexVisibilityGenerator(
                    index: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    visible: Generators::constant(true)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/270' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Command\RenameToGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/271' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Command\RenameElementGenerator(
                    kind: Command\ElementKindGenerator::selecting([MySql\Alter\Command\ElementKind::Index]),
                    from: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                    ),
                    to: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                    )
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/272' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Command\ConvertCharsetGenerator(
                    charset: new \Tests\Generator\MySql\Statement\Name\CharsetNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('binary'),
                            quote: Generators::constant(null)
                        )
                    ),
                    collation: Generators::constant(null)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/273' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Command\ReorderGenerator(
                    items: \Tests\Generator\Operands::tuple(
                        new \Tests\Generator\MySql\Statement\Query\OrderItemGenerator(
                            expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                    quote: Generators::constant(null)
                                ),
                                qualifier: Generators::constant(null),
                                dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                            )]),
                            direction: Generators::constant(null)
                        )
                    )
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/274' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Modifier\AlgorithmOptionGenerator(
                    algorithm: Generators::constant(null)
                ),
                new Partition\TablespaceCommandGenerator(
                    import: Generators::constant(false),
                    partitions: Generators::constant(null)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/275' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Modifier\LockOptionGenerator(
                    lock: Generators::constant(null)
                ),
                new Partition\TablespaceCommandGenerator(
                    import: Generators::constant(false),
                    partitions: Generators::constant(null)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/277' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Column\AddColumnGenerator(
                    column: new \Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator(
                        name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                            column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            table: Generators::constant(null),
                            dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                        ),
                        specification: new \Tests\Generator\MySql\Statement\Table\Column\OrdinaryColumnGenerator(
                            type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                                kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([MySql\Type\Kind\ElementaryKind::Bit]),
                                length: Generators::constant(null)
                            )]),
                            attributes: Generators::constant([]),
                            references: Generators::constant(null)
                        )
                    ),
                    position: new Column\ColumnPositionGenerator(
                        after: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        )
                    ),
                    columnWritten: Generators::constant(false)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/379' => new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Column\DefaultSettingGenerator(
                    column: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                    ),
                    default: new \Tests\Generator\MySql\Statement\Table\Column\DefaultLiteralGenerator(
                        value: new \Tests\Generator\MySql\Statement\LiteralGenerator([new \Tests\Generator\MySql\Statement\Literal\SignedLiteralGenerator(
                            negative: Generators::constant(false),
                            number: new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )
                        )])
                    ),
                    columnWritten: Generators::constant(false)
                )
            ),
            ignore: Generators::constant(false)
        );
    }

    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{GrammarRelease, ErisGenerator<MySql\Alter\AlterTable>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'AlterTable/additional/63' => [GrammarRelease::MySql847, new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('t'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Column\AddColumnGenerator(
                    column: new \Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator(
                        name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                            column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('c'),
                                quote: Generators::constant(null)
                            ),
                            table: Generators::constant(null),
                            dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                        ),
                        specification: new \Tests\Generator\MySql\Statement\Table\Column\GeneratedColumnGenerator(
                            type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(
                                kind: \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([MySql\Type\Kind\IntegralKind::Int]),
                                width: Generators::constant(null),
                                modifiers: Generators::constant([])
                            )]),
                            expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticGenerator(
                                operator: \Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticOperatorGenerator::selecting([MySql\Expression\Operator\ArithmeticOperator::Plus]),
                                left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                        value: Generators::constant('a'),
                                        quote: Generators::constant(null)
                                    ),
                                    qualifier: Generators::constant(null),
                                    dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                                )]),
                                right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                        value: Generators::constant('d'),
                                        quote: Generators::constant(null)
                                    ),
                                    qualifier: Generators::constant(null),
                                    dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                                )])
                            )]),
                            collation: Generators::constant(null),
                            storage: Generators::constant(null),
                            attributes: Generators::constant([]),
                            references: Generators::constant(null)
                        )
                    ),
                    position: Generators::constant(null),
                    columnWritten: Generators::constant(false)
                ),
                new Column\AddColumnGenerator(
                    column: new \Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator(
                        name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                            column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('d'),
                                quote: Generators::constant(null)
                            ),
                            table: Generators::constant(null),
                            dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                        ),
                        specification: new \Tests\Generator\MySql\Statement\Table\Column\OrdinaryColumnGenerator(
                            type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(
                                kind: \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([MySql\Type\Kind\IntegralKind::Int]),
                                width: Generators::constant(null),
                                modifiers: Generators::constant([])
                            )]),
                            attributes: Generators::constant([]),
                            references: Generators::constant(null)
                        )
                    ),
                    position: Generators::constant(null),
                    columnWritten: Generators::constant(false)
                )
            ),
            ignore: Generators::constant(false)
        )];
        yield 'AlterTable/additional/64' => [GrammarRelease::MySql5744, new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('t'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Partition\UpgradePartitioningGenerator()
            ),
            ignore: Generators::constant(false)
        )];
        yield 'AlterTable/additional/65' => [GrammarRelease::MySql847, new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('t'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Column\AddColumnGenerator(
                    column: new \Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator(
                        name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                            column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('b'),
                                quote: Generators::constant(null)
                            ),
                            table: Generators::constant(null),
                            dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                        ),
                        specification: new \Tests\Generator\MySql\Statement\Table\Column\OrdinaryColumnGenerator(
                            type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(
                                kind: \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([MySql\Type\Kind\IntegralKind::Int]),
                                width: Generators::constant(null),
                                modifiers: Generators::constant([])
                            )]),
                            attributes: Generators::constant([]),
                            references: Generators::constant(null)
                        )
                    ),
                    position: Generators::constant(null),
                    columnWritten: Generators::constant(false)
                ),
                new Partition\PartitionByGenerator(
                    partitioning: new \Tests\Generator\MySql\Statement\Partition\PartitionClauseGenerator(
                        method: new \Tests\Generator\MySql\Statement\Partition\Method\HashMethodGenerator(
                            linear: Generators::constant(false),
                            expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticGenerator(
                                operator: \Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticOperatorGenerator::selecting([MySql\Expression\Operator\ArithmeticOperator::Plus]),
                                left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticGenerator(
                                    operator: \Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticOperatorGenerator::selecting([MySql\Expression\Operator\ArithmeticOperator::Plus]),
                                    left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                            value: Generators::constant('a'),
                                            quote: Generators::constant(null)
                                        ),
                                        qualifier: Generators::constant(null),
                                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                                    )]),
                                    right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                            value: Generators::constant('b'),
                                            quote: Generators::constant(null)
                                        ),
                                        qualifier: Generators::constant(null),
                                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                                    )])
                                )]),
                                right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                        value: Generators::constant('c'),
                                        quote: Generators::constant(null)
                                    ),
                                    qualifier: Generators::constant(null),
                                    dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                                )])
                            )])
                        ),
                        partitions: Generators::constant(null),
                        subpartitioning: Generators::constant(null),
                        definitions: Generators::constant([])
                    )
                )
            ),
            ignore: Generators::constant(false)
        )];
        yield 'AlterTable/additional/73' => [GrammarRelease::MySql847, new AlterTableGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('t'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: \Tests\Generator\Operands::tuple(
                new Partition\PartitionByGenerator(
                    partitioning: new \Tests\Generator\MySql\Statement\Partition\PartitionClauseGenerator(
                        method: new \Tests\Generator\MySql\Statement\Partition\Method\ExpressionMethodGenerator(
                            kind: \Tests\Generator\MySql\Statement\Partition\Method\PartitionKindGenerator::selecting([MySql\Partition\Method\PartitionKind::Range]),
                            expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('a'),
                                    quote: Generators::constant(null)
                                ),
                                qualifier: Generators::constant(null),
                                dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([MySql\Expression\OptionalWords::Omitted])
                            )])
                        ),
                        partitions: Generators::constant(null),
                        subpartitioning: new \Tests\Generator\MySql\Statement\Partition\SubpartitioningGenerator(
                            method: new \Tests\Generator\MySql\Statement\Partition\Method\KeyMethodGenerator(
                                linear: Generators::constant(false),
                                algorithm: Generators::constant(null),
                                columns: \Tests\Generator\Operands::tuple(
                                    \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                        value: Generators::constant('b'),
                                        quote: Generators::constant(null)
                                    )
                                )
                            ),
                            count: Generators::constant(null)
                        ),
                        definitions: Generators::constant([])
                    )
                )
            ),
            ignore: Generators::constant(false)
        )];
    }

}
