<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * `ON db.*`: the database level of a named database.
 *
 * The database name may contain the `_` and `%` wildcards of a pattern, as
 * the grant tables store it; the name is kept as written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-database-privileges.
 *
 * @visibility SqlSemantics
 */
final class DatabaseLevel implements PrivilegeLevel
{
    use Snapshot;

    /**
     * @param Name $database The database name
     */
    public function __construct(public readonly Name $database)
    {
    }

    /**
     * Describes the level.
     */
    public function describe(): string
    {
        return 'database ' . $this->database->value;
    }

    /**
     * Writes `db.*`.
     */
    public function render(Output $out): void
    {
        $out->name($this->database, NameUse::Qualifier)->glue()->symbol('.')->glue()->symbol('*');
    }
}
