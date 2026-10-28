<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Generates INT declarations and duplicate-column requests, preserving target qualifiers.
 *
 * @implements ErisGenerator<CreateTable>
 */
final class CreateTableGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<CreateTable>
     */
    private ErisGenerator $delegate;

    /**
     * @param ErisGenerator<ColumnName> $columnNames The declaration names to exercise
     */
    public function __construct(ErisGenerator $columnNames, bool $duplicateColumns = false, bool $ifNotExists = false)
    {
        $this->delegate = Generators::map(
            static function (ColumnName $columnName) use ($duplicateColumns, $ifNotExists): CreateTable {
                $elements = [new ColumnDefinition($columnName, new OrdinaryColumn(new Integral(IntegralKind::Int)))];
                if ($duplicateColumns) {
                    $elements[] = new ColumnDefinition(new ColumnName($columnName->column, $columnName->table, $columnName->dot), new OrdinaryColumn(new Integral(IntegralKind::Int)));
                }
                return new CreateTable($columnName->table ?? new QualifiedName(new Name('target')), $elements, ifNotExists: $ifNotExists);
            },
            $columnNames,
        );
    }

    /**
     * @param int $size The generation size chosen by Eris
     * @return GeneratedValueSingle<CreateTable>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<CreateTable>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<QualifiedName> $name The generated semantic operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Table\TableElement>> $elements The generated semantic operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Table\TableOption>> $options The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Partitioning|null> $partitioning The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\TableQuery|null> $query The generated semantic operand
     * @param ErisGenerator<int> $temporaryWords The generated semantic operand
     * @param ErisGenerator<bool> $ifNotExists The generated semantic operand
     * @param ErisGenerator<bool> $enclosed The generated semantic operand
     * @param ErisGenerator<list<bool>>|null $optionsCommas Generated boundaries between table options
     * @return ErisGenerator<CreateTable>
     */
    public static function fromOperands(ErisGenerator $name, ErisGenerator $elements, ErisGenerator $options, ErisGenerator $partitioning, ErisGenerator $query, ErisGenerator $temporaryWords, ErisGenerator $ifNotExists, ErisGenerator $enclosed, ?ErisGenerator $optionsCommas = null): ErisGenerator
    {
        return \Tests\Generator\Operands::bind(\Tests\Generator\Operands::tuple($name, $elements, $options, $partitioning, $query, $temporaryWords, $ifNotExists, $enclosed), static function (array $parts) use ($optionsCommas): ErisGenerator {
            return Generators::map(static fn (array $commas): CreateTable => new CreateTable(name: $parts[0], elements: $parts[1], options: $parts[2], partitioning: $parts[3], query: $parts[4], temporaryWords: $parts[5], ifNotExists: $parts[6], enclosed: $parts[7], optionsCommas: $commas), $optionsCommas ?? Generators::vector(max(0, count($parts[2]) - 1), Generators::bool()));
        });
    }

    /**
     * Generates valid table options together with their independent optional notation.
     *
     * @return ErisGenerator<array{array{CreateTable, list<\SqlSemantics\Platform\MySql\Statement\Table\TableOption>, \SqlSemantics\Contract\AnalysisContext, list<bool>}, CreateTable}>
     */
    public static function optionDefinitions(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $definitions = new self(new \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator($release));
        $options = \Tests\Generator\Operands::tuple(
            new Option\EngineOptionGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::constant('InnoDB'), Generators::constant(null))),
            new Option\CharsetOptionGenerator(new \Tests\Generator\MySql\Statement\Name\CharsetNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::constant('latin1'), Generators::constant(null)))),
            new Option\TextOptionGenerator(Generators::constant(\SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\TextOptionKind::Comment), new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::map(static fn (int $value): string => 'comment ' . $value, Generators::choose(0, 1000)))),
            new Option\RowFormatOptionGenerator(Generators::elements([\SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat::Dynamic, \SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat::Compact])),
        );
        return \Tests\Generator\Operands::bind(\Tests\Generator\Operands::tuple($definitions, $options, \Tests\Generator\Semantics\Contract\AnalysisContextGenerator::mysql($release), Generators::vector(3, Generators::bool())), static function (array $parts): ErisGenerator {
            $statement = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($parts[2], new CreateTable($parts[0]->name, $parts[0]->elements, $parts[1], optionsCommas: $parts[3]));
            \SqlSemantics\Diagnostic\Check::invariant($statement instanceof CreateTable, 'Generated options remain a complete table declaration.');
            return Generators::constant([$parts, $statement]);
        });
    }

    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<CreateTable>>
     */
    public static function forms(): iterable
    {
        yield 'CreateTable/27' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: Generators::constant([]),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/136' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: Generators::constant([]),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/181' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: Generators::constant([]),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: \Tests\Generator\Operands::tuple(
                new Option\StorageOptionGenerator(
                    medium: Column\Kind\StorageMediumGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\StorageMedium::Disk])
                )
            ),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/182' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: Generators::constant([]),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: \Tests\Generator\Operands::tuple(
                new Option\StorageOptionGenerator(
                    medium: Column\Kind\StorageMediumGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\StorageMedium::Disk])
                )
            ),
            partitioning: new \Tests\Generator\MySql\Statement\Partition\PartitionClauseGenerator(
                method: new \Tests\Generator\MySql\Statement\Partition\Method\KeyMethodGenerator(
                    linear: Generators::constant(false),
                    algorithm: Generators::constant(null),
                    columns: Generators::constant([])
                ),
                partitions: Generators::constant(null),
                subpartitioning: Generators::constant(null),
                definitions: Generators::constant([])
            ),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/183' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: Generators::constant([]),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: \Tests\Generator\Operands::tuple(
                new Option\StorageOptionGenerator(
                    medium: Column\Kind\StorageMediumGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\StorageMedium::Disk])
                )
            ),
            partitioning: new \Tests\Generator\MySql\Statement\Partition\PartitionClauseGenerator(
                method: new \Tests\Generator\MySql\Statement\Partition\Method\KeyMethodGenerator(
                    linear: Generators::constant(false),
                    algorithm: Generators::constant(null),
                    columns: Generators::constant([])
                ),
                partitions: Generators::constant(null),
                subpartitioning: Generators::constant(null),
                definitions: Generators::constant([])
            ),
            query: new TableQueryGenerator(
                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ExplicitTableGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )]),
                duplicate: \Tests\Generator\MySql\Statement\Dml\DuplicateHandlingGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling::Replace])
            ),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/197' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: Generators::constant([]),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: \Tests\Generator\Operands::tuple(
                new Option\EngineOptionGenerator(
                    engine: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    )
                )
            ),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/198' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: Generators::constant([]),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: \Tests\Generator\Operands::tuple(
                new Option\SecondaryEngineOptionGenerator(
                    engine: Generators::constant(null)
                )
            ),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/199' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: Generators::constant([]),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: \Tests\Generator\Operands::tuple(
                new Option\NumberOptionGenerator(
                    kind: Option\Kind\NumberOptionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\NumberOptionKind::MaxRows]),
                    value: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                        text: Generators::constant('1'),
                        hexadecimal: Generators::constant(false)
                    )
                )
            ),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/200' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: Generators::constant([]),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: \Tests\Generator\Operands::tuple(
                new Option\TextOptionGenerator(
                    kind: Option\Kind\TextOptionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\TextOptionKind::Password]),
                    value: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                        value: Generators::constant('text'),
                        radix: Generators::constant(null)
                    )
                )
            ),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/201' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: Generators::constant([]),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: \Tests\Generator\Operands::tuple(
                new Option\RowFormatOptionGenerator(
                    format: Option\Kind\RowFormatGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat::Default])
                )
            ),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/202' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: Generators::constant([]),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: \Tests\Generator\Operands::tuple(
                new Option\UnionOptionGenerator(
                    tables: Generators::constant([])
                )
            ),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/203' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: Generators::constant([]),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: \Tests\Generator\Operands::tuple(
                new Option\CharsetOptionGenerator(
                    charset: new \Tests\Generator\MySql\Statement\Name\CharsetNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('binary'),
                            quote: Generators::constant(null)
                        )
                    )
                )
            ),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/204' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: Generators::constant([]),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: \Tests\Generator\Operands::tuple(
                new Option\CollationOptionGenerator(
                    collation: new \Tests\Generator\MySql\Statement\Name\CollationNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('binary'),
                            quote: Generators::constant(null)
                        )
                    )
                )
            ),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/205' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: Generators::constant([]),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: \Tests\Generator\Operands::tuple(
                new Option\InsertMethodOptionGenerator(
                    method: Option\Kind\InsertMethodGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\InsertMethod::No])
                )
            ),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/206' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: Generators::constant([]),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: \Tests\Generator\Operands::tuple(
                new Option\TablespaceOptionGenerator(
                    tablespace: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    )
                )
            ),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/207' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: Generators::constant([]),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: \Tests\Generator\Operands::tuple(
                new Option\StartTransactionGenerator()
            ),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/208' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: Generators::constant([]),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: \Tests\Generator\Operands::tuple(
                new Option\AutoextendSizeOptionGenerator(
                    size: new \Tests\Generator\MySql\Statement\Literal\ByteSizeGenerator(
                        number: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                            text: Generators::constant('1'),
                            hexadecimal: Generators::constant(false)
                        ),
                        word: Generators::constant(null)
                    )
                )
            ),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/209' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Key\IndexDefinitionGenerator(
                    kind: Key\IndexKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::FullText]),
                    parts: \Tests\Generator\Operands::tuple(
                        new Key\ColumnPartGenerator(
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
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/210' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: Generators::constant([]),
                        references: new Key\ReferencesGenerator(
                            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                    quote: Generators::constant(null)
                                ),
                                schema: Generators::constant(null),
                                catalog: Generators::constant(null)
                            ),
                            columns: Generators::constant(null),
                            match: Generators::constant(null),
                            actions: Generators::constant([])
                        )
                    )
                )
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/211' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Key\ForeignKeyGenerator(
                    columns: \Tests\Generator\Operands::tuple(
                        new Key\ColumnPartGenerator(
                            column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            length: Generators::constant(null),
                            direction: Generators::constant(null)
                        )
                    ),
                    references: new Key\ReferencesGenerator(
                        table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            schema: Generators::constant(null),
                            catalog: Generators::constant(null)
                        ),
                        columns: Generators::constant(null),
                        match: Generators::constant(null),
                        actions: Generators::constant([])
                    ),
                    name: Generators::constant(null),
                    constraint: Generators::constant(null)
                )
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/212' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Key\CheckConstraintGenerator(
                    condition: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                        operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                        function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                        arguments: Generators::constant([]),
                                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                    )])
                                )])
                            )])
                        )])
                    )]),
                    name: Generators::constant(null),
                    enforced: Generators::constant(null)
                )
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/213' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Key\IndexDefinitionGenerator(
                    kind: Key\IndexKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Primary]),
                    parts: \Tests\Generator\Operands::tuple(
                        new Key\ColumnPartGenerator(
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
                    constraint: new Key\ConstraintNameGenerator(
                        name: Generators::constant(null)
                    ),
                    keyword: Generators::constant(null)
                )
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/226' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: \Tests\Generator\Operands::tuple(
                            new Column\KeywordAttributeGenerator(
                                keyword: Column\Kind\ColumnKeywordGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword::Null])
                            )
                        ),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/227' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: \Tests\Generator\Operands::tuple(
                            new Column\KeywordAttributeGenerator(
                                keyword: Column\Kind\ColumnKeywordGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword::Null])
                            ),
                            new Column\DefaultExpressionGenerator(
                                expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                    function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                    arguments: Generators::constant([]),
                                                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                )])
                                            )])
                                        )])
                                    )])
                                )])
                            )
                        ),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/228' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: \Tests\Generator\Operands::tuple(
                            new Column\KeywordAttributeGenerator(
                                keyword: Column\Kind\ColumnKeywordGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword::Null])
                            ),
                            new Column\CommentAttributeGenerator(
                                comment: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                                    value: Generators::constant('text'),
                                    radix: Generators::constant(null)
                                )
                            )
                        ),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/229' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: \Tests\Generator\Operands::tuple(
                            new Column\KeywordAttributeGenerator(
                                keyword: Column\Kind\ColumnKeywordGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword::Null])
                            ),
                            new Column\CollateAttributeGenerator(
                                collation: new \Tests\Generator\MySql\Statement\Name\CollationNameGenerator(
                                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                        value: Generators::constant('binary'),
                                        quote: Generators::constant(null)
                                    )
                                )
                            )
                        ),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/230' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: \Tests\Generator\Operands::tuple(
                            new Column\KeywordAttributeGenerator(
                                keyword: Column\Kind\ColumnKeywordGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword::Null])
                            ),
                            new Column\FormatAttributeGenerator(
                                format: Column\Kind\ColumnFormatGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnFormat::Default])
                            )
                        ),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/231' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: \Tests\Generator\Operands::tuple(
                            new Column\KeywordAttributeGenerator(
                                keyword: Column\Kind\ColumnKeywordGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword::Null])
                            ),
                            new Column\StorageAttributeGenerator(
                                medium: Column\Kind\StorageMediumGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\StorageMedium::Default])
                            )
                        ),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/232' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: \Tests\Generator\Operands::tuple(
                            new Column\KeywordAttributeGenerator(
                                keyword: Column\Kind\ColumnKeywordGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword::Null])
                            ),
                            new Column\DefaultLiteralGenerator(
                                value: new \Tests\Generator\MySql\Statement\LiteralGenerator([new \Tests\Generator\MySql\Statement\Literal\NullLiteralGenerator()])
                            )
                        ),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/236' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Key\ForeignKeyGenerator(
                    columns: \Tests\Generator\Operands::tuple(
                        new Key\ColumnPartGenerator(
                            column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            length: Generators::constant(null),
                            direction: Generators::constant(null)
                        )
                    ),
                    references: new Key\ReferencesGenerator(
                        table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            schema: Generators::constant(null),
                            catalog: Generators::constant(null)
                        ),
                        columns: Generators::constant(null),
                        match: Key\ReferenceMatchGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceMatch::Full]),
                        actions: Generators::constant([])
                    ),
                    name: Generators::constant(null),
                    constraint: Generators::constant(null)
                )
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/237' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Key\ForeignKeyGenerator(
                    columns: \Tests\Generator\Operands::tuple(
                        new Key\ColumnPartGenerator(
                            column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            length: Generators::constant(null),
                            direction: Generators::constant(null)
                        )
                    ),
                    references: new Key\ReferencesGenerator(
                        table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            schema: Generators::constant(null),
                            catalog: Generators::constant(null)
                        ),
                        columns: Generators::constant(null),
                        match: Generators::constant(null),
                        actions: \Tests\Generator\Operands::tuple(
                            new Key\ReferentialActionGenerator(
                                event: Key\ReferenceEventGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceEvent::Update]),
                                option: Key\ReferenceOptionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceOption::Restrict])
                            )
                        )
                    ),
                    name: Generators::constant(null),
                    constraint: Generators::constant(null)
                )
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
        yield 'CreateTable/426' => CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\ElementaryGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit]),
                            length: Generators::constant(null)
                        )]),
                        attributes: \Tests\Generator\Operands::tuple(
                            new Key\CheckConstraintGenerator(
                                condition: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                    function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                    arguments: Generators::constant([]),
                                                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                )])
                                            )])
                                        )])
                                    )])
                                )]),
                                name: Generators::constant(null),
                                enforced: Generators::constant(null)
                            ),
                            new Column\EnforcementAttributeGenerator(
                                enforced: Generators::constant(true)
                            )
                        ),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        );
    }

    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<CreateTable>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'CreateTable/additional/67' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('t'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('a'),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\TemporalGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\TemporalKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind::Timestamp]),
                            precision: Generators::constant(null),
                            modifiers: Generators::constant([])
                        )]),
                        attributes: \Tests\Generator\Operands::tuple(
                            new Column\OnUpdateGenerator(
                                value: new Column\Value\CurrentTimestampGenerator(
                                    precision: Generators::constant(null),
                                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                )
                            )
                        ),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        )];
        yield 'CreateTable/additional/68' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('t'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('a'),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([IntegralKind::Int]),
                            width: Generators::constant(null),
                            modifiers: Generators::constant([])
                        )]),
                        attributes: \Tests\Generator\Operands::tuple(
                            new Column\EngineAttributeGenerator(
                                attribute: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                                    value: Generators::constant('{}'),
                                    radix: Generators::constant(null)
                                ),
                                secondary: Generators::constant(true)
                            )
                        ),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        )];
        yield 'CreateTable/additional/69' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('t'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('g'),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\SpatialGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\SpatialKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind::Point])
                        )]),
                        attributes: \Tests\Generator\Operands::tuple(
                            new Column\SridAttributeGenerator(
                                srid: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                                    text: Generators::constant('4326'),
                                    hexadecimal: Generators::constant(false)
                                )
                            )
                        ),
                        references: Generators::constant(null)
                    )
                )
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        )];
        yield 'CreateTable/additional/71' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateTableGenerator::fromOperands(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('t'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: \Tests\Generator\Operands::tuple(
                new Column\ColumnDefinitionGenerator(
                    name: \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(
                        column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('a'),
                            quote: Generators::constant(null)
                        ),
                        table: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    specification: new Column\OrdinaryColumnGenerator(
                        type: new \Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator([new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(
                            kind: \Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([IntegralKind::Int]),
                            width: Generators::constant(null),
                            modifiers: Generators::constant([])
                        )]),
                        attributes: Generators::constant([]),
                        references: Generators::constant(null)
                    )
                ),
                new Key\IndexDefinitionGenerator(
                    kind: Key\IndexKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Index]),
                    parts: \Tests\Generator\Operands::tuple(
                        new Key\ColumnPartGenerator(
                            column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('a'),
                                quote: Generators::constant(null)
                            ),
                            length: Generators::constant(null),
                            direction: Generators::constant(null)
                        )
                    ),
                    name: Generators::constant(null),
                    algorithm: Generators::constant(null),
                    options: \Tests\Generator\Operands::tuple(
                        new Key\Option\IndexEngineAttributeGenerator(
                            attribute: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                                value: Generators::constant('{}'),
                                radix: Generators::constant(null)
                            ),
                            secondary: Generators::constant(false)
                        )
                    ),
                    constraint: Generators::constant(null),
                    keyword: Generators::constant(null)
                )
            ),
            options: Generators::constant([]),
            partitioning: Generators::constant(null),
            query: Generators::constant(null),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            enclosed: Generators::constant(false)
        )];
    }

}
