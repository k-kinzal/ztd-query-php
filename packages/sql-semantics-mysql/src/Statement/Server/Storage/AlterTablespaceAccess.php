<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\TablespaceAccess;

/**
 * `ALTER TABLESPACE name {READ_ONLY | READ_WRITE | NOT ACCESSIBLE}`: a request to change the access mode of a tablespace (MySQL 5.6 and 5.7).
 *
 * Mirrors the ALTER_ACCESS_MODE_TABLESPACE command of st_alter_tablespace.
 * Rule: MYSQL-ALTER-TABLESPACE-ACCESS-001. No storage engine of these
 * releases implements it. A tablespace is no relation, so the statement
 * neither declares nor resolves one.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/alter-tablespace.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterTablespaceAccess implements Statement
{
    use Snapshot;

    /**
     * @param Name $name The tablespace name
     * @param TablespaceAccess $access The access mode
     */
    public function __construct(public readonly Name $name, public readonly TablespaceAccess $access)
    {
    }

    /**
     * Has nothing to derive: a tablespace is no relation.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'TABLESPACE')->name($this->name, NameUse::Identifier)->keyword(...explode(' ', $this->access->value));
    }
}
