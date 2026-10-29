<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Maintenance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
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
     * @param 0|3|4 $kind The concrete maintenance command
     * @return ErisGenerator<array{M\Table\CreateTable, \SqlSemantics\Statement\AnalyzedStatement}>
     */
    public static function withTable(GrammarRelease $release, int $kind): ErisGenerator
    {
        $definitions = new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, \Tests\Generator\MySql\Statement\Table\CreateTableGenerator::operands(new \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator($release)));
        return Generators::bind($definitions, static function (\SqlSemantics\Statement\AnalyzedStatement $definition) use ($release, $kind): ErisGenerator {
            \SqlSemantics\Diagnostic\Check::invariant($definition instanceof M\Table\CreateTable, 'Maintenance depends on a generated table declaration.');
            $tables = Generators::vector(1, new MaintainedTableGenerator(Generators::constant($definition->name)));
            $binlog = \Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator::optional();
            $requests = match ($kind) {
                0 => self::operands($binlog, $tables), 3 => OptimizeTableGenerator::operands($binlog, $tables), 4 => RepairTableGenerator::operands($binlog, $tables)
            };
            $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, $release->value);
            $context = $semantics->context($definition->declarations(), true, new \SqlSemantics\Contract\SearchPath($definition->name->schema->value ?? 'pbt'));
            return Generators::map(static fn (\SqlSemantics\Statement\AnalyzedStatement $request): array => [$definition, $request], new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $requests, Generators::constant($context)));
        });
    }

    /**
     * Generates complete AnalyzeTable values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<M\Server\Maintenance\AnalyzeTable>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release === GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            if ($release === GrammarRelease::MySql847) {
                $forms[] = Generators::map(static fn (array $parts): M\Statement => $parts[count($parts) - 1], \Tests\Generator\MySql\Statement\Server\Problem\HistogramTablesGenerator::statements());
                $forms[] = Generators::map(static fn (array $parts): M\Statement => $parts[count($parts) - 1], \Tests\Generator\MySql\Statement\Server\Problem\UnknownHistogramColumnGenerator::statements());
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No AnalyzeTable forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): M\Server\Maintenance\AnalyzeTable {
            if (!$statement instanceof M\Server\Maintenance\AnalyzeTable) {
                throw new LogicException('Publishing AnalyzeTable must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<M\Server\BinlogOption|null> $binlog Generated constructor operand
     * @param ErisGenerator<list<M\Server\Maintenance\MaintainedTable>> $tables Generated constructor operand
     * @param ErisGenerator<M\Server\Maintenance\Histogram|null>|null $histogram Generated constructor operand
     * @param ErisGenerator<M\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<M\Table\TableWord>|null $tableWord The generated table-list keyword
     * @return ErisGenerator<M\Server\Maintenance\AnalyzeTable>
     */
    public static function operands(ErisGenerator $binlog, ErisGenerator $tables, ?ErisGenerator $histogram = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $tableWord = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): M\Server\Maintenance\AnalyzeTable {
            return new M\Server\Maintenance\AnalyzeTable(binlog: $parts[0], tables: $parts[1], histogram: $parts[2], statementInformation: $parts[3], inputSource: $parts[4], tableWord: $parts[5]);
        }, Generators::tuple($binlog, $tables, $histogram ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $tableWord ?? new \Tests\Generator\MySql\Statement\Table\TableWordGenerator()));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<M\Server\Maintenance\AnalyzeTable>>
     */
    public static function forms(): iterable
    {
        yield 'AnalyzeTable/14' => AnalyzeTableGenerator::operands(
            binlog: \Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator::optional(),
            tables: Generators::tuple(
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
        yield 'AnalyzeTable/285' => AnalyzeTableGenerator::operands(
            binlog: \Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator::optional(),
            tables: Generators::tuple(
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
                columns: Generators::tuple(
                    \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    )
                ),
                buckets: Generators::constant(null),
                update: Generators::constant(null)
            )])
        );
        yield 'AnalyzeTable/286' => AnalyzeTableGenerator::operands(
            binlog: \Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator::optional(),
            tables: Generators::tuple(
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
                columns: Generators::tuple(
                    \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    )
                ),
                buckets: Generators::constant(null),
                update: HistogramUpdateGenerator::selecting([M\Server\Maintenance\HistogramUpdate::Manual])
            )])
        );
        yield 'AnalyzeTable/287' => AnalyzeTableGenerator::operands(
            binlog: \Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator::optional(),
            tables: Generators::tuple(
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
                columns: Generators::tuple(
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
        yield 'AnalyzeTable/288' => AnalyzeTableGenerator::operands(
            binlog: \Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator::optional(),
            tables: Generators::tuple(
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
                columns: Generators::tuple(
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
     * @param int<0, 4> $kind The concrete maintenance command
     * @return ErisGenerator<array{M\Statement, M\Statement, int, int}>
     */
    public static function repeatedTargets(GrammarRelease $release, bool $selected, int $kind): ErisGenerator
    {
        return Generators::map(static function (array $parts) use ($release, $selected): array {
            [$form, $name, $qualification] = $parts;
            $tables = [new M\Server\Maintenance\MaintainedTable(new QualifiedName($name, $qualification === 0 ? null : new Name('pbt'))), new M\Server\Maintenance\MaintainedTable(new QualifiedName(new Name($name->value), $qualification === 0 ? null : new Name('pbt'))), new M\Server\Maintenance\MaintainedTable(new QualifiedName(new Name('later'), $qualification === 2 ? new Name('pbt') : null))];
            $request = match ($form) {
                0 => new M\Server\Maintenance\AnalyzeTable(null, $tables),
                1 => new M\Server\Maintenance\CheckTable($tables),
                2 => new M\Server\Maintenance\ChecksumTable($tables),
                3 => new M\Server\Maintenance\OptimizeTable(null, $tables),
                4 => new M\Server\Maintenance\RepairTable(null, $tables),
            };
            $definition = new M\Routine\CreateProcedure(new QualifiedName(new Name('routine_' . $name->value), new Name('pbt')), new M\Routine\ParameterList([]), $request);
            $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new Name('pbt')]);
            $publication = new \SqlSemantics\Platform\MySql\Construction\Publication();
            return [$publication->publish($context, $request), $publication->publish($context, $definition), $form === 1 ? 1314 : 1066, !$selected && $qualification === 0 ? 1046 : 1066];
        }, Generators::tuple(Generators::constant($kind), \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::map(static fn (int $value): string => 'target_' . $value, Generators::choose(0, 1000)), Generators::constant(null)), Generators::choose(0, 2)));
    }

}
