<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;

/**
 * `DROP {DATABASE | SCHEMA} [IF EXISTS] name`: a request to remove a database and every table in it.
 *
 * Mirrors SQLCOM_DROP_DB. Rule: MYSQL-DROP-DATABASE-001. The statement
 * removes no declaration from the context it is analyzed against.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-database.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class DropDatabase implements Statement
{
    use Snapshot;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param Name $name The database name
     */
    public function __construct(public readonly bool $ifExists, public readonly Name $name)
    {
    }

    /**
     * Has nothing to derive: a database is no relation.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', 'DATABASE');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->name($this->name, NameUse::Qualifier);
    }
}
