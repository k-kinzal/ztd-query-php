<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level;

use SqlSemantics\Statement\Node;

/**
 * The level of the ON clause of GRANT and REVOKE: `*.*`, `*`, `db.*`, or a table or routine.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-privilege-levels.
 *
 * @visibility public
 * @example Telling the global level apart
 *     new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel() instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel // => true
 */
interface PrivilegeLevel extends Node
{
    /**
     * Describes the level for a person, as the manual names it.
     */
    public function describe(): string;
}
