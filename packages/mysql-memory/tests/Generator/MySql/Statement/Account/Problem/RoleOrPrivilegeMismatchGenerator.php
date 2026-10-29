<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RoleOrPrivilegeMismatch values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch>
 */
final class RoleOrPrivilegeMismatchGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch> */
    use Composition;

    /**
     * @param ErisGenerator<string> $item Generated constructor operand
     * @param ErisGenerator<bool> $roleExpected Generated constructor operand
     * @param ErisGenerator<int|null>|null $offset Generated constructor operand
     */
    public function __construct(ErisGenerator $item, ErisGenerator $roleExpected, ?ErisGenerator $offset = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch(item: $parts[0], roleExpected: $parts[1], offset: $parts[2]);
        }, Generators::tuple(
            $item,
            $roleExpected,
            $offset ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Account\\Problem\\RoleOrPrivilegeMismatch' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RoleOrPrivilegeMismatchGenerator(
            item: Generators::constant('UPDATE'),
            roleExpected: Generators::constant(true),
            offset: Generators::constant(9)
        )];
    }

}
