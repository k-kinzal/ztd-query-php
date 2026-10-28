<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DropTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\DropTable>
 */
final class DropTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\DropTable> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $temporary Generated constructor operand
     * @param ErisGenerator<bool> $ifExists Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Alter\TargetTable>> $tables Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\DropBehavior|null>|null $behavior Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\TableWord>|null $tableWord The generated table-list keyword
     */
    public function __construct(ErisGenerator $temporary, ErisGenerator $ifExists, ErisGenerator $tables, ?ErisGenerator $behavior = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $tableWord = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\DropTable {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\DropTable(temporary: $parts[0], ifExists: $parts[1], tables: $parts[2], behavior: $parts[3], statementInformation: $parts[4], inputSource: $parts[5], tableWord: $parts[6]);
        }, \Tests\Generator\Operands::tuple(
            $temporary,
            $ifExists,
            $tables,
            $behavior ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $tableWord ?? new \Tests\Generator\MySql\Statement\Table\TableWordGenerator()
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\DropTable>>
     */
    public static function forms(): iterable
    {
        yield 'DropTable/41' => new DropTableGenerator(
            temporary: Generators::constant(false),
            ifExists: Generators::constant(false),
            tables: \Tests\Generator\Operands::tuple(
                new TargetTableGenerator(
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
            behavior: Generators::constant(null)
        );
        yield 'DropTable/276' => new DropTableGenerator(
            temporary: Generators::constant(false),
            ifExists: Generators::constant(false),
            tables: \Tests\Generator\Operands::tuple(
                new TargetTableGenerator(
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
            behavior: DropBehaviorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Alter\DropBehavior::Restrict])
        );
    }

}
