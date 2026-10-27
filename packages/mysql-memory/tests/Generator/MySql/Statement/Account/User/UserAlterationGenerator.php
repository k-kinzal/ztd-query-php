<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\User;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UserAlteration values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\UserAlteration>
 */
final class UserAlterationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\User\UserAlteration> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\UserAlteration>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = \Tests\Generator\Operands::oneOf($alternatives);
    }
}
