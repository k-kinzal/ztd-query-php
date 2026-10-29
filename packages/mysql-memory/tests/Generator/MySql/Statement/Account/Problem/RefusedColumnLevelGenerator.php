<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedColumnLevel values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedColumnLevel>
 */
final class RefusedColumnLevelGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedColumnLevel> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege> $privilege Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel|\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\DatabaseLevel|\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel> $level Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName|null>|null $program Generated constructor operand
     */
    public function __construct(ErisGenerator $privilege, ErisGenerator $level, ?ErisGenerator $program = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedColumnLevel {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedColumnLevel(privilege: $parts[0], level: $parts[1], program: $parts[2]);
        }, Generators::tuple(
            $privilege,
            $level,
            $program ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedColumnLevel>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Account\\Problem\\RefusedColumnLevel' => [\SqlSemantics\Contract\GrammarRelease::MySql5651, new RefusedColumnLevelGenerator(
            privilege: new \Tests\Generator\MySql\Statement\Account\Privilege\Item\StaticPrivilegeGenerator(
                kind: \Tests\Generator\MySql\Statement\Account\Privilege\Item\PrivilegeKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Select]),
                columns: Generators::tuple(
                    \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $suffix): string => 'a' . $suffix, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    )
                )
            ),
            level: new \Tests\Generator\MySql\Statement\Account\Privilege\Level\DatabaseLevelGenerator(
                database: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 'd' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                )
            ),
            program: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 'p' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            )
        )];
    }

}
