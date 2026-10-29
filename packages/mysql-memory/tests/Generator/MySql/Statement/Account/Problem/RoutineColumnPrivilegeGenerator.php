<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RoutineColumnPrivilege values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\RoutineColumnPrivilege>
 */
final class RoutineColumnPrivilegeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Problem\RoutineColumnPrivilege> */
    use Composition;

    /**
     * @param ErisGenerator<int> $offset Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel> $level Generated constructor operand
     */
    public function __construct(ErisGenerator $offset, ErisGenerator $level)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Problem\RoutineColumnPrivilege {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\RoutineColumnPrivilege(offset: $parts[0], level: $parts[1]);
        }, Generators::tuple(
            $offset,
            $level
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\RoutineColumnPrivilege>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Account\\Problem\\RoutineColumnPrivilege' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RoutineColumnPrivilegeGenerator(
            offset: Generators::constant(38),
            level: new \Tests\Generator\MySql\Statement\Account\Privilege\Level\PrivilegeLevelGenerator([new \Tests\Generator\MySql\Statement\Account\Privilege\Level\ObjectLevelGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $suffix): string => 'p' . $suffix, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                )
            )])
        )];
    }

}
