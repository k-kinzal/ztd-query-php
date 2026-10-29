<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes MisplacedPrivilege values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege>
 */
final class MisplacedPrivilegeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege> */
    use Composition;

    /**
     * @param ErisGenerator<string> $privilege Generated constructor operand
     * @param ErisGenerator<string> $level Generated constructor operand
     * @param ErisGenerator<string> $error Generated constructor operand
     */
    public function __construct(ErisGenerator $privilege, ErisGenerator $level, ErisGenerator $error)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege(privilege: $parts[0], level: $parts[1], error: $parts[2]);
        }, Generators::tuple(
            $privilege,
            $level,
            $error
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Account\\Problem\\MisplacedPrivilege' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new MisplacedPrivilegeGenerator(
            privilege: Generators::constant('AS clause'),
            level: Generators::constant('database db'),
            error: Generators::constant('ER_UNSUPPORTED_USE_OF_GRANT_AS')
        )];
    }

}
