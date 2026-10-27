<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Variable;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UserVariableBinding values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\UserVariableBinding>
 */
final class UserVariableBindingGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Variable\UserVariableBinding> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<bool> $exists Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $exists)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Variable\UserVariableBinding {
            return new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariableBinding(name: $parts[0], exists: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $exists
        ));
    }
}
