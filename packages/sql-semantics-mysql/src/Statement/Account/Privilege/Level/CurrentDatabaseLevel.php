<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * `ON *`: the database level of the session's current database.
 *
 * Without a current database the server rejects the statement
 * (ER_NO_DB_ERROR).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-database-privileges.
 *
 * @visibility public
 * @example Describing the level
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel())->describe() // => 'the current database'
 */
final class CurrentDatabaseLevel implements PrivilegeLevel
{
    use Snapshot;

    /**
     * Describes the level.
     */
    public function describe(): string
    {
        return 'the current database';
    }

    /**
     * Writes `*`.
     */
    public function render(Output $out): void
    {
        $out->symbol('*');
    }
}
