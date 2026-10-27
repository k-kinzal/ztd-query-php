<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes GrantAs values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantAs>
 */
final class GrantAsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantAs> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account> $user Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\RoleSelection|null>|null $roles Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $user, ?ErisGenerator $roles = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantAs {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantAs(user: $parts[0], roles: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $user,
            $roles ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
