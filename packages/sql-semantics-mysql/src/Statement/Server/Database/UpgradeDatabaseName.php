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
 * `ALTER DATABASE name UPGRADE DATA DIRECTORY NAME`: a request to rename a database directory to the encoding of MySQL 5.1 (MySQL 5.6 and 5.7).
 *
 * Mirrors SQLCOM_ALTER_DB_UPGRADE. Rule: MYSQL-UPGRADE-DATABASE-001. The
 * name is that of the database as written after the old `#mysql50#` prefix.
 * The statement changes no declaration.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/alter-database.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class UpgradeDatabaseName implements Statement
{
    use Snapshot;

    /**
     * @param Name $name The database name
     */
    public function __construct(public readonly Name $name)
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
        $out->keyword('ALTER', 'DATABASE')->name($this->name, NameUse::Qualifier)->keyword('UPGRADE', 'DATA', 'DIRECTORY', 'NAME');
    }
}
