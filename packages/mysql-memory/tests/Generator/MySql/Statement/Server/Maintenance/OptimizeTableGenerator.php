<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Maintenance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes OptimizeTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\OptimizeTable>
 */
final class OptimizeTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\OptimizeTable> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $noWriteToBinlog Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable>> $tables Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $noWriteToBinlog, ErisGenerator $tables, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\OptimizeTable {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\OptimizeTable(noWriteToBinlog: $parts[0], tables: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $noWriteToBinlog,
            $tables,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\OptimizeTable>>
     */
    public static function forms(): iterable
    {
        yield 'OptimizeTable/57' => new OptimizeTableGenerator(
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
            )
        );
    }

}
