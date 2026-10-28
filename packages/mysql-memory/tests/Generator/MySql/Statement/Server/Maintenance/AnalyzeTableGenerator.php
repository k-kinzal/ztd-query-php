<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Maintenance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AnalyzeTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable>
 */
final class AnalyzeTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable> */
    use Composition;

    /**
     * Composes maintenance with the declaration it will execute against.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Table\CreateTable, \SqlSemantics\Statement\AnalyzedStatement}>
     */
    public static function withTable(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $definitions = new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, new \Tests\Generator\MySql\Statement\Table\CreateTableGenerator(new \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator($release)));
        return \Tests\Generator\Operands::bind($definitions, static function (\SqlSemantics\Statement\AnalyzedStatement $definition) use ($release): ErisGenerator {
            \SqlSemantics\Diagnostic\Check::invariant($definition instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable, 'Maintenance depends on a generated table declaration.');
            $tables = Generators::vector(1, new MaintainedTableGenerator(Generators::constant($definition->name)));
            $binlog = \Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator::optional();
            $requests = new \Tests\Generator\MySql\Statement\StatementGenerator([new self($binlog, $tables), new OptimizeTableGenerator($binlog, $tables), new RepairTableGenerator($binlog, $tables)]);
            $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, $release->value);
            $context = $semantics->context($definition->declarations(), true, new \SqlSemantics\Contract\SearchPath($definition->name->schema->value ?? 'pbt'));
            return Generators::map(static fn (\SqlSemantics\Statement\AnalyzedStatement $request): array => [$definition, $request], new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $requests, Generators::constant($context)));
        });
    }

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\BinlogOption|null> $binlog Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable>> $tables Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\Histogram|null>|null $histogram Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\TableWord>|null $tableWord The generated table-list keyword
     */
    public function __construct(ErisGenerator $binlog, ErisGenerator $tables, ?ErisGenerator $histogram = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $tableWord = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable(binlog: $parts[0], tables: $parts[1], histogram: $parts[2], statementInformation: $parts[3], inputSource: $parts[4], tableWord: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $binlog,
            $tables,
            $histogram ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $tableWord ?? new \Tests\Generator\MySql\Statement\Table\TableWordGenerator()
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable>>
     */
    public static function forms(): iterable
    {
        yield 'AnalyzeTable/14' => new AnalyzeTableGenerator(
            binlog: \Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator::optional(),
            tables: \Tests\Generator\Operands::tuple(
                new MaintainedTableGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )
            ),
            histogram: Generators::constant(null)
        );
        yield 'AnalyzeTable/285' => new AnalyzeTableGenerator(
            binlog: \Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator::optional(),
            tables: \Tests\Generator\Operands::tuple(
                new MaintainedTableGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )
            ),
            histogram: new HistogramGenerator([new UpdateHistogramGenerator(
                columns: \Tests\Generator\Operands::tuple(
                    \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    )
                ),
                buckets: Generators::constant(null),
                update: Generators::constant(null)
            )])
        );
        yield 'AnalyzeTable/286' => new AnalyzeTableGenerator(
            binlog: \Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator::optional(),
            tables: \Tests\Generator\Operands::tuple(
                new MaintainedTableGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )
            ),
            histogram: new HistogramGenerator([new UpdateHistogramGenerator(
                columns: \Tests\Generator\Operands::tuple(
                    \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    )
                ),
                buckets: Generators::constant(null),
                update: HistogramUpdateGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\HistogramUpdate::Manual])
            )])
        );
        yield 'AnalyzeTable/287' => new AnalyzeTableGenerator(
            binlog: \Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator::optional(),
            tables: \Tests\Generator\Operands::tuple(
                new MaintainedTableGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )
            ),
            histogram: new HistogramGenerator([new LoadHistogramGenerator(
                columns: \Tests\Generator\Operands::tuple(
                    \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    )
                ),
                data: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('text'),
                    radix: Generators::constant(null)
                )
            )])
        );
        yield 'AnalyzeTable/288' => new AnalyzeTableGenerator(
            binlog: \Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator::optional(),
            tables: \Tests\Generator\Operands::tuple(
                new MaintainedTableGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )
            ),
            histogram: new HistogramGenerator([new DropHistogramGenerator(
                columns: \Tests\Generator\Operands::tuple(
                    \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    )
                )
            )])
        );
    }

}
