<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Lock;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes LockTables values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables>
 */
final class LockTablesGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Lock\TableLock>> $locks Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $locks, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables(locks: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $locks,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables>>
     */
    public static function forms(): iterable
    {
        yield 'LockTables/389' => new LockTablesGenerator(
            locks: \Tests\Generator\Operands::tuple(
                new TableLockGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    ),
                    alias: Generators::constant(null),
                    mode: LockModeGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Lock\LockMode::Read]),
                    mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                )
            )
        );
    }

}
