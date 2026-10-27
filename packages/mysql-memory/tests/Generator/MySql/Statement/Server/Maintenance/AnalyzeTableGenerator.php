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
     * @param ErisGenerator<bool> $noWriteToBinlog Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable>> $tables Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\Histogram|null>|null $histogram Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $noWriteToBinlog, ErisGenerator $tables, ?ErisGenerator $histogram = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable(noWriteToBinlog: $parts[0], tables: $parts[1], histogram: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $noWriteToBinlog,
            $tables,
            $histogram ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
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
            noWriteToBinlog: Generators::constant(false),
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
            noWriteToBinlog: Generators::constant(false),
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
            noWriteToBinlog: Generators::constant(false),
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
            noWriteToBinlog: Generators::constant(false),
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
            noWriteToBinlog: Generators::constant(false),
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
