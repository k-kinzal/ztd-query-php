<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Snapshot;

/**
 * Whether a user-variable entry exists when an occurrence is resolved.
 *
 * An earlier expression assignment allocates an entry without evaluating its value.
 * This differs from both an absent variable and an entry explicitly holding NULL.
 * MySQL 5.6 reads an absent occurrence as NULL throughout the statement. Modern
 * control functions infer its operand type from the other branches.
 * Verified through SQL on MySQL 5.6.51, 8.0.44, 8.4.7 and 9.1.0.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/user-variables.html.
 *
 * @visibility public
 * @example Describing an absent variable entry
 *     $binding = new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariableBinding(new \SqlSemantics\Statement\Identifier\Name('v'), false);
 *     [$binding->name->value, $binding->exists] // => ['v', false]
 */
final class UserVariableBinding implements Resolution
{
    use Snapshot;

    /**
     * @param Name $name The variable name without the at sign
     * @param bool $exists Whether the session or an earlier resolved assignment provides an entry
     */
    public function __construct(public readonly Name $name, public readonly bool $exists)
    {
    }
}
