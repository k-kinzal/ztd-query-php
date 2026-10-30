<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
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
     * Generates complete AlterTable values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<MySql\Alter\AlterTable>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = $release->database() === 'mysql' ? [self::partitionDefinitions()] : [];
            if ($release->database() === 'mysql') {
                $counts = Generators::oneOf(new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(Generators::oneOf(Generators::map(static fn (int $count): string => (string) $count, Generators::choose(0, 16)), Generators::elements('4294967294', '4294967295', '18446744073709551615'))), \Tests\Generator\MySql\Statement\Literal\NumeralGenerator::refusedIntegers());
                $partition = Generators::oneOf(new Partition\CoalescePartitionGenerator(Generators::constant(null), $counts), new Partition\AddPartitionGenerator(Generators::constant(null), Generators::constant([]), $counts));
                $forms[] = self::operands(new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator()), Generators::tuple($partition));
                $columns = \Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator::sized();
                $commands = Generators::oneOf(new Column\AddColumnGenerator($columns), new Column\ChangeColumnGenerator(Generators::constant(null), $columns));
                $forms[] = self::operands(new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator()), Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => Generators::vector($count, $commands)));
            }
            if (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                $forms[] = self::operands(new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), Generators::constant(null), Generators::constant(null)), Generators::constant([]), dot: new \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator());
            }
            if ($release === GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }
            foreach (self::additionalForms() as [$grammar, $form]) {
                if ($grammar === $release) {
                    $forms[] = $form;
                }
            }

            if ($release === GrammarRelease::MySql847) {
                $forms[] = Generators::map(static fn (array $parts): MySql\Statement => $parts[1], Problem\DuplicateColumnGenerator::statements());
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No AlterTable forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (AnalyzedStatement $statement): MySql\Alter\AlterTable {
            if (!$statement instanceof MySql\Alter\AlterTable) {
                throw new LogicException('Publishing AlterTable must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * Composes a generated table, its WRITE lock and a dependent column or index addition.
     * The target and selected column remain related during Eris shrinking.
     *
     * @return ErisGenerator<array{MySql\Table\CreateTable, MySql\Server\Lock\LockTables, MySql\Alter\AlterTable}>
     */
    public static function withLockedPrerequisites(GrammarRelease $release): ErisGenerator
    {
        $definitions = new \Tests\Generator\MySql\Statement\Table\CreateTableGenerator($release, \Tests\Generator\MySql\Statement\Table\CreateTableGenerator::integers());
        return Generators::bind($definitions, static function (MySql\Table\CreateTable $create) use ($release): ErisGenerator {
            $contexts = Generators::constant(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')], $create->declarations()));
            $lock = new \Tests\Generator\MySql\Statement\Server\Lock\TableLockGenerator(Generators::constant($create->name), Generators::constant(null), \Tests\Generator\MySql\Statement\Server\Lock\LockModeGenerator::selecting([MySql\Server\Lock\LockMode::Write]));
            $commands = Generators::map(static function (array $parts): array {
                [$index, $suffix, $column] = $parts;
                $name = new Name('added_' . $suffix);
                return [$index
                    ? new MySql\Alter\Command\AddConstraint(new MySql\Table\Key\IndexDefinition(MySql\Table\Key\IndexKind::Index, [new MySql\Table\Key\ColumnPart(new Name('column_' . $column))], new MySql\Name\ColumnName($name)))
                    : new MySql\Alter\Column\AddColumn(new MySql\Table\Column\ColumnDefinition(new MySql\Name\ColumnName($name), new MySql\Table\Column\OrdinaryColumn(new MySql\Type\Integral(MySql\Type\Kind\IntegralKind::Int))))];
            }, Generators::tuple(Generators::bool(), Generators::choose(0, 10000), Generators::choose(0, count($create->elements) - 1)));
            return Generators::tuple(
                Generators::constant($create),
                new \Tests\Generator\MySql\Statement\Server\Lock\LockTablesGenerator($release, \Tests\Generator\MySql\Statement\Server\Lock\LockTablesGenerator::operands(Generators::tuple($lock)), $contexts),
                new self($release, self::operands(Generators::constant($create->name), $commands), $contexts),
            );
        });
    }

    /**
     * Varies partition methods independently from complete bound definitions.
     * Missing values and method disagreements must reach SQL execution.
     *
     * @return ErisGenerator<MySql\Alter\AlterTable>
     */
    public static function partitionDefinitions(): ErisGenerator
    {
        $names = new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator();
        $methods = Generators::map(static fn (int $kind): MySql\Partition\Method\PartitionMethod => match ($kind) {
            0 => new MySql\Partition\Method\HashMethod(false, new MySql\Name\ColumnUse(new Name('i'))),
            1 => new MySql\Partition\Method\KeyMethod(false, null, [new Name('i')]),
            2, 3 => new MySql\Partition\Method\ExpressionMethod($kind === 2 ? MySql\Partition\Method\PartitionKind::Range : MySql\Partition\Method\PartitionKind::List, new MySql\Name\ColumnUse(new Name('i'))),
            default => new MySql\Partition\Method\ColumnsMethod($kind === 4 ? MySql\Partition\Method\PartitionKind::Range : MySql\Partition\Method\PartitionKind::List, [new Name('i')]),
        }, Generators::choose(0, 5));
        $rows = new \Tests\Generator\MySql\Statement\Partition\Bound\PartitionRowGenerator(
            Generators::vector(1, new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 20))))
        );
        $bounds = Generators::oneOf(
            Generators::constant(null),
            new \Tests\Generator\MySql\Statement\Partition\Bound\LessThanGenerator($rows),
            new \Tests\Generator\MySql\Statement\Partition\Bound\ValuesInGenerator($rows)
        );
        $definition = new \Tests\Generator\MySql\Statement\Partition\PartitionDefinitionGenerator($names, $bounds, Generators::constant([]), Generators::constant([]));
        $definitions = Generators::bind(Generators::choose(1, 4), static fn (int $count): ErisGenerator => Generators::vector($count, $definition));
        return self::operands(
            new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator($names, Generators::oneOf(Generators::constant(null), $names), Generators::constant(null)),
            Generators::tuple(new Partition\PartitionByGenerator(new \Tests\Generator\MySql\Statement\Partition\PartitionClauseGenerator($methods, Generators::constant(null), Generators::constant(null), $definitions)))
        );
    }

    /**
     * @param ErisGenerator<QualifiedName> $table Generated constructor operand
     * @param ErisGenerator<list<MySql\Alter\AlterCommand>> $commands Generated constructor operand
     * @param ErisGenerator<bool>|null $ignore Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     * @param ErisGenerator<MySql\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<MySql\Expression\OptionalWords>|null $dot Generated leading-dot spelling
     * @return ErisGenerator<MySql\Alter\AlterTable>
     */
    public static function operands(ErisGenerator $table, ErisGenerator $commands, ?ErisGenerator $ignore = null, ?ErisGenerator $relationInformation = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $dot = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): MySql\Alter\AlterTable {
            return new MySql\Alter\AlterTable(table: $parts[0], commands: $parts[1], ignore: $parts[2], relationInformation: $parts[3], statementInformation: $parts[4], inputSource: $parts[5], dot: $parts[6]);
        }, Generators::tuple($table, $commands, $ignore ?? Generators::constant(false), $relationInformation ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $dot ?? Generators::constant(MySql\Expression\OptionalWords::Omitted)));
    }
    /**
     * Composes existing InnoDB storage and a dependent DISCARD request.
     * Shrinking a table name, partition count or row rebuilds the dependent values.
     *
     * @return ErisGenerator<array{AnalyzedStatement, AnalyzedStatement, AnalyzedStatement}>
     */
    public static function tablespaceSequences(GrammarRelease $release): ErisGenerator
    {
        $counts = $release === GrammarRelease::MySql5651 ? Generators::constant(0) : Generators::elements([0, 2, 3, 4]);
        $selection = Generators::bind($counts, static fn (int $count): ErisGenerator => Generators::tuple(Generators::constant($count), Generators::choose(-1, max(-1, $count - 1))));
        $numbers = new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $number): string => (string) $number, Generators::choose(0, 20)));
        $values = Generators::oneOf($numbers, new \Tests\Generator\MySql\Statement\Expression\Operator\UnaryGenerator(Generators::constant(MySql\Expression\Operator\UnaryOperator::Minus), $numbers));
        $rows = Generators::bind(Generators::choose(1, 10), static fn (int $count): ErisGenerator => Generators::vector($count, new \Tests\Generator\MySql\Statement\Dml\Insert\InsertedRowGenerator(Generators::tuple($values))));
        return Generators::map(static function (array $parts) use ($release): array {
            [$name, $column, [$count, $selected], $rows] = $parts;
            $table = new QualifiedName($name);
            $field = new MySql\Table\Column\ColumnDefinition(new MySql\Name\ColumnName($column), new MySql\Table\Column\OrdinaryColumn(new MySql\Type\Integral(MySql\Type\Kind\IntegralKind::Int)));
            $partitioning = $count === 0 ? null : new MySql\Partition\PartitionClause(new MySql\Partition\Method\HashMethod(false, new MySql\Name\ColumnUse($column)), new MySql\Literal\Numeral((string) $count), null, []);
            $create = new MySql\Table\CreateTable($table, [$field], partitioning: $partitioning);
            $context = new AnalysisContext(new LanguageProfile($release), [new Name('pbt')], session: new MySql\Type\Resolved\Settings(MySql\Type\Resolved\Collation::known('utf8mb4_unicode_ci')));
            $publication = new Publication();
            $definition = $publication->publish($context, $create);
            $context = new AnalysisContext($context->profile, [new Name('pbt')], $definition->declarations(), session: $context->session);
            $insert = new MySql\Dml\Insert\InsertRows(new MySql\Dml\Insert\InsertInto(false, null, false, new MySql\Dml\WriteTarget($table)), $rows);
            $partitions = $selected < 0 ? null : new MySql\Partition\NamedPartitions([new Name('p' . $selected)]);
            $alter = new MySql\Alter\AlterTable($table, [new MySql\Alter\Partition\TablespaceCommand(false, $partitions)]);
            return [$definition, $publication->publish($context, $insert), $publication->publish($context, $alter)];
        }, Generators::tuple(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), $selection, $rows));
    }

    /**
     * Generates a table, its rows and a dependent index alteration before execution.
     * Shrinking the names, engine, temporary scope or rows rebuilds all dependent values.
     *
     * @return ErisGenerator<array{AnalyzedStatement, AnalyzedStatement, AnalyzedStatement}>
     */
    public static function indexSequences(GrammarRelease $release): ErisGenerator
    {
        $values = new \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator(MySql\Type\Resolved\Collation::known('utf8mb4_unicode_ci'), Generators::map(static fn (int $value): string => (string) $value, Generators::choose(-10000, 10000)));
        $rows = Generators::bind(Generators::choose(1, 5), static fn (int $count): ErisGenerator => Generators::vector($count, new \Tests\Generator\MySql\Statement\Dml\Insert\InsertedRowGenerator(Generators::vector(2, $values))));
        $operands = Generators::tuple(
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
            return [$definition, $publication->publish($context, $insert), $publication->publish($context, $alter)];
        }, $operands);
    }

    /**
     * Composes functional-index and virtual-column changes with the table and rows they need.
     *
     * @return ErisGenerator<array{AnalyzedStatement, AnalyzedStatement, AnalyzedStatement}>
     */
    public static function functionalSequences(GrammarRelease $release): ErisGenerator
    {
        $values = new \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator(MySql\Type\Resolved\Collation::known('utf8mb4_unicode_ci'), Generators::map(static fn (int $value): string => (string) $value, Generators::choose(-10000, 10000)));
        $rows = Generators::bind(Generators::choose(1, 5), static fn (int $count): ErisGenerator => Generators::vector($count, new \Tests\Generator\MySql\Statement\Dml\Insert\InsertedRowGenerator(Generators::vector(1, $values))));
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
            return [$definition, $publication->publish($context, $insert), $publication->publish($context, new MySql\Alter\AlterTable($table, $commands))];
        }, Generators::tuple(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), $rows, Generators::choose(0, 4)));
    }

    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<MySql\Alter\AlterTable>>
     */
    public static function forms(): iterable
    {
        yield 'AlterTable/11' => AlterTableGenerator::operands(
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
        yield 'AlterTable/184' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Partition\PartitionByGenerator(
                    partitioning: new \Tests\Generator\MySql\Statement\Partition\PartitionClauseGenerator(
                        method: new \Tests\Generator\MySql\Statement\Partition\Method\ColumnsMethodGenerator(
                            kind: \Tests\Generator\MySql\Statement\Partition\Method\PartitionKindGenerator::selecting([MySql\Partition\Method\PartitionKind::Range]),
                            columns: Generators::tuple(
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
        yield 'AlterTable/185' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
                                columns: Generators::tuple(
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
        yield 'AlterTable/186' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Partition\PartitionByGenerator(
                    partitioning: new \Tests\Generator\MySql\Statement\Partition\PartitionClauseGenerator(
                        method: new \Tests\Generator\MySql\Statement\Partition\Method\KeyMethodGenerator(
                            linear: Generators::constant(false),
                            algorithm: Generators::constant(null),
                            columns: Generators::constant([])
                        ),
                        partitions: Generators::constant(null),
                        subpartitioning: Generators::constant(null),
                        definitions: Generators::tuple(
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
        yield 'AlterTable/187' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Partition\AddPartitionGenerator(
                    binlog: Generators::constant(null),
                    definitions: Generators::tuple(
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
        yield 'AlterTable/188' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Partition\AddPartitionGenerator(
                    binlog: Generators::constant(null),
                    definitions: Generators::tuple(
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
        yield 'AlterTable/189' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Partition\AddPartitionGenerator(
                    binlog: Generators::constant(null),
                    definitions: Generators::tuple(
                        new \Tests\Generator\MySql\Statement\Partition\PartitionDefinitionGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            values: new \Tests\Generator\MySql\Statement\Partition\Bound\ValuesInGenerator(
                                row: new \Tests\Generator\MySql\Statement\Partition\Bound\PartitionRowGenerator(
                                    items: Generators::tuple(
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
        yield 'AlterTable/190' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Partition\AddPartitionGenerator(
                    binlog: Generators::constant(null),
                    definitions: Generators::tuple(
                        new \Tests\Generator\MySql\Statement\Partition\PartitionDefinitionGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            values: new \Tests\Generator\MySql\Statement\Partition\Bound\LessThanGenerator(
                                row: new \Tests\Generator\MySql\Statement\Partition\Bound\PartitionRowGenerator(
                                    items: Generators::tuple(
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
        yield 'AlterTable/191' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Partition\AddPartitionGenerator(
                    binlog: Generators::constant(null),
                    definitions: Generators::tuple(
                        new \Tests\Generator\MySql\Statement\Partition\PartitionDefinitionGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            values: new \Tests\Generator\MySql\Statement\Partition\Bound\ValuesInRowsGenerator(
                                rows: Generators::tuple(
                                    new \Tests\Generator\MySql\Statement\Partition\Bound\PartitionRowGenerator(
                                        items: Generators::tuple(
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
        yield 'AlterTable/192' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Partition\AddPartitionGenerator(
                    binlog: Generators::constant(null),
                    definitions: Generators::tuple(
                        new \Tests\Generator\MySql\Statement\Partition\PartitionDefinitionGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            values: Generators::constant(null),
                            options: Generators::constant([]),
                            subpartitions: Generators::tuple(
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
        yield 'AlterTable/193' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Partition\AddPartitionGenerator(
                    binlog: Generators::constant(null),
                    definitions: Generators::tuple(
                        new \Tests\Generator\MySql\Statement\Partition\PartitionDefinitionGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            values: Generators::constant(null),
                            options: Generators::tuple(
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
        yield 'AlterTable/196' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Command\SetTableOptionsGenerator(
                    options: Generators::tuple(
                        new \Tests\Generator\MySql\Statement\Table\Option\StorageOptionGenerator(
                            medium: \Tests\Generator\MySql\Statement\Table\Column\Kind\StorageMediumGenerator::selecting([MySql\Table\Column\Kind\StorageMedium::Disk])
                        )
                    )
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/214' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/244' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/253' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/254' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Partition\RemovePartitioningGenerator()
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/255' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Modifier\ValidationOptionGenerator(
                    validate: Generators::constant(true)
                ),
                new Command\ForceGenerator()
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/256' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Partition\DropPartitionGenerator(
                    partitions: new \Tests\Generator\MySql\Statement\Partition\NamedPartitionsGenerator(
                        names: Generators::tuple(
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
        yield 'AlterTable/257' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/258' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/259' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Partition\ReorganizePartitionGenerator(
                    binlog: Generators::constant(null),
                    partitions: Generators::constant(null),
                    definitions: Generators::constant([])
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/260' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/261' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Partition\SecondaryLoadGenerator(
                    load: Generators::constant(true),
                    partitions: Generators::constant([])
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/262' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/263' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Column\AddColumnsGenerator(
                    elements: Generators::tuple(
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
        yield 'AlterTable/264' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Command\AddConstraintGenerator(
                    element: new \Tests\Generator\MySql\Statement\Table\Key\IndexDefinitionGenerator(
                        kind: \Tests\Generator\MySql\Statement\Table\Key\IndexKindGenerator::selecting([MySql\Table\Key\IndexKind::FullText]),
                        parts: Generators::tuple(
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
        yield 'AlterTable/265' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/266' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/267' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Command\ToggleKeysGenerator(
                    enable: Generators::constant(false)
                )
            ),
            ignore: Generators::constant(false)
        );
        yield 'AlterTable/268' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/269' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/270' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/271' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/272' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/273' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Command\ReorderGenerator(
                    items: Generators::tuple(
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
        yield 'AlterTable/274' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/275' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/277' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/379' => AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/additional/63' => [GrammarRelease::MySql847, AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('t'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/additional/64' => [GrammarRelease::MySql5744, AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('t'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
                new Partition\UpgradePartitioningGenerator()
            ),
            ignore: Generators::constant(false)
        )];
        yield 'AlterTable/additional/65' => [GrammarRelease::MySql847, AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('t'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
        yield 'AlterTable/additional/73' => [GrammarRelease::MySql847, AlterTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('t'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            commands: Generators::tuple(
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
                                columns: Generators::tuple(
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
