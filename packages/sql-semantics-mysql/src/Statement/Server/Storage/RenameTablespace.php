<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;

/**
 * `ALTER TABLESPACE name RENAME TO new_name`: a request to rename a general tablespace (MySQL 8.0 and later).
 *
 * Mirrors PT_alter_tablespace_rename (Sql_cmd_alter_tablespace_rename).
 * Rule: MYSQL-RENAME-TABLESPACE-001. A tablespace is no relation, so the
 * statement neither declares nor resolves one.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-tablespace.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class RenameTablespace implements Statement
{
    use Snapshot;

    /**
     * @param Name $name The tablespace name
     * @param Name $target The new name
     */
    public function __construct(public readonly Name $name, public readonly Name $target)
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
        $out->keyword('ALTER', 'TABLESPACE')->name($this->name, NameUse::Identifier)->keyword('RENAME', 'TO')->name($this->target, NameUse::Identifier);
    }
}
