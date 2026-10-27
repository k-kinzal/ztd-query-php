<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\User;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RoleSet values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet>
 */
final class RoleSetGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet> */
    use Composition;

    /**
     * Selects an enum alternative through Eris.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(\SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet::cases());
    }
    /**
     * Selects the alternatives valid in a particular SQL role.
     *
     * @param non-empty-list<\SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet> $cases The alternatives allowed by the parent structure
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet>
     */
    public static function selecting(array $cases): ErisGenerator
    {
        return Generators::elements($cases);
    }

}
