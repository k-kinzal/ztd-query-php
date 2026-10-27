<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Maintenance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RepairTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\RepairTable>
 */
final class RepairTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\RepairTable> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $noWriteToBinlog Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable>> $tables Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\RepairOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $noWriteToBinlog, ErisGenerator $tables, ?ErisGenerator $options = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\RepairTable {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\RepairTable(noWriteToBinlog: $parts[0], tables: $parts[1], options: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $noWriteToBinlog,
            $tables,
            $options ?? Generators::constant([]),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\RepairTable>>
     */
    public static function forms(): iterable
    {
        yield 'RepairTable/64' => new RepairTableGenerator(
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
            options: Generators::constant([])
        );
        yield 'RepairTable/284' => new RepairTableGenerator(
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
            options: \Tests\Generator\Operands::tuple(
                \Tests\Generator\MySql\Statement\Server\RepairOptionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\RepairOption::Quick])
            )
        );
    }

}
