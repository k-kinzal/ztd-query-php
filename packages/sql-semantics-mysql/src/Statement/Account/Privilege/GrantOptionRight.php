<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * `WITH GRANT OPTION` of GRANT: the grantees may grant the privileges they receive at that level to others.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-privileges.
 *
 * @visibility SqlSemantics
 */
final class GrantOptionRight implements WithOption
{
    use Snapshot;

    /**
     * Writes GRANT OPTION.
     */
    public function render(Output $out): void
    {
        $out->keyword('GRANT', 'OPTION');
    }
}
