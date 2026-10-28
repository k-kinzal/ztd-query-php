<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Maintenance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ChecksumTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumTable>
 */
final class ChecksumTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumTable> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable>> $tables Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumMode|null>|null $mode Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\TableWord>|null $tableWord The generated table-list keyword
     */
    public function __construct(ErisGenerator $tables, ?ErisGenerator $mode = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $tableWord = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumTable {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumTable(tables: $parts[0], mode: $parts[1], statementInformation: $parts[2], inputSource: $parts[3], tableWord: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $tables,
            $mode ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $tableWord ?? new \Tests\Generator\MySql\Statement\Table\TableWordGenerator()
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumTable>>
     */
    public static function forms(): iterable
    {
        yield 'ChecksumTable/19' => new ChecksumTableGenerator(
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
            mode: Generators::constant(null)
        );
        yield 'ChecksumTable/283' => new ChecksumTableGenerator(
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
            mode: ChecksumModeGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumMode::Quick])
        );
    }

}
