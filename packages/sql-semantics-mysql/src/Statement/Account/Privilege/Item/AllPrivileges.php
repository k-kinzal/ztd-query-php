<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * `ALL [PRIVILEGES]`: every privilege of the level the grant names, except GRANT OPTION and, globally, PROXY.
 *
 * It is written alone in the privilege list. PRIVILEGES is an optional word.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/privileges-provided.html#priv_all.
 *
 * @visibility public
 * @example Granting every privilege of a database
 *     (new \SqlSemantics\Platform\MySql\Semantics())->analyze('GRANT ALL PRIVILEGES ON db.* TO u')->toString() // => 'GRANT ALL ON db.* TO u'
 */
final class AllPrivileges implements Grantable
{
    use Snapshot;

    /**
     * Writes ALL.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALL');
    }
}
