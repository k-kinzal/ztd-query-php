<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Instance;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * `RELOAD KEYRING`: reinitialize the keyring component from its configuration (MySQL 8.0.24 and later).
 *
 * Mirrors RELOAD_KEYRING.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-instance.html.
 *
 * @visibility SqlSemantics
 */
final class ReloadKeyring implements InstanceAction
{
    use Snapshot;

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('RELOAD', 'KEYRING');
    }
}
