<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Maintenance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
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
     * @return ErisGenerator<array{M\Table\CreateTable, \SqlSemantics\Statement\AnalyzedStatement}>
     */
    public static function withTable(GrammarRelease $release): ErisGenerator
    {
        $definitions = new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, new \Tests\Generator\MySql\Statement\Table\CreateTableGenerator(new \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator($release)));
        return \Tests\Generator\Operands::bind($definitions, static function (\SqlSemantics\Statement\AnalyzedStatement $definition) use ($release): ErisGenerator {
            \SqlSemantics\Diagnostic\Check::invariant($definition instanceof M\Table\CreateTable, 'Maintenance depends on a generated table declaration.');
            $tables = Generators::vector(1, new MaintainedTableGenerator(Generators::constant($definition->name)));
            $binlog = \Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator::optional();
            $requests = new \Tests\Generator\MySql\Statement\StatementGenerator([new self($binlog, $tables), new OptimizeTableGenerator($binlog, $tables), new RepairTableGenerator($binlog, $tables)]);
            $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, $release->value);
            $context = $semantics->context($definition->declarations(), true, new \SqlSemantics\Contract\SearchPath($definition->name->schema->value ?? 'pbt'));
            return Generators::map(static fn (\SqlSemantics\Statement\AnalyzedStatement $request): array => [$definition, $request], new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $requests, Generators::constant($context)));
        });
    }

    /**
     * @param ErisGenerator<M\Server\BinlogOption|null> $binlog Generated constructor operand
     * @param ErisGenerator<list<M\Server\Maintenance\MaintainedTable>> $tables Generated constructor operand
     * @param ErisGenerator<M\Server\Maintenance\Histogram|null>|null $histogram Generated constructor operand
     * @param ErisGenerator<M\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<M\Table\TableWord>|null $tableWord The generated table-list keyword
     */
    public function __construct(ErisGenerator $binlog, ErisGenerator $tables, ?ErisGenerator $histogram = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $tableWord = null)
    {
        $this->delegate = Generators::map(static function (array $parts): M\Server\Maintenance\AnalyzeTable {
            return new M\Server\Maintenance\AnalyzeTable(binlog: $parts[0], tables: $parts[1], histogram: $parts[2], statementInformation: $parts[3], inputSource: $parts[4], tableWord: $parts[5]);
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
     * @return iterable<string, ErisGenerator<M\Server\Maintenance\AnalyzeTable>>
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
                update: HistogramUpdateGenerator::selecting([M\Server\Maintenance\HistogramUpdate::Manual])
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


    /**
     * Composes repeated targets with later operands and the complete stored definition.
     *
     * @return ErisGenerator<array{M\Statement, M\Statement, int, int}>
     */
    public static function repeatedTargets(GrammarRelease $release, bool $selected): ErisGenerator
    {
        return Generators::map(static function (array $parts) use ($release, $selected): array {
            [$form, $name, $qualification] = $parts;
            $tables = [new M\Server\Maintenance\MaintainedTable(new QualifiedName($name, $qualification === 0 ? null : new Name('pbt'))), new M\Server\Maintenance\MaintainedTable(new QualifiedName(new Name($name->value), $qualification === 0 ? null : new Name('pbt'))), new M\Server\Maintenance\MaintainedTable(new QualifiedName(new Name('later'), $qualification === 2 ? new Name('pbt') : null))];
            $request = match ($form) {
                0 => new M\Server\Maintenance\AnalyzeTable(null, $tables),
                1 => new M\Server\Maintenance\CheckTable($tables),
                2 => new M\Server\Maintenance\ChecksumTable($tables),
                3 => new M\Server\Maintenance\OptimizeTable(null, $tables),
                default => new M\Server\Maintenance\RepairTable(null, $tables),
            };
            $definition = new M\Routine\CreateProcedure(new QualifiedName(new Name('routine_' . $name->value), new Name('pbt')), new M\Routine\ParameterList([]), $request);
            $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new Name('pbt')]);
            $publication = new \SqlSemantics\Platform\MySql\Construction\Publication();
            return [$publication->publish($context, $request), $publication->publish($context, $definition), $form === 1 ? 1314 : 1066, !$selected && $qualification === 0 ? 1046 : 1066];
        }, \Tests\Generator\Operands::tuple(Generators::choose(0, 4), \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::map(static fn (int $value): string => 'target_' . $value, Generators::choose(0, 1000)), Generators::constant(null)), Generators::choose(0, 2)));
    }

}
