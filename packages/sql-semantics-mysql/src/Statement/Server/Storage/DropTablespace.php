<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\Server\StorageOptions;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `DROP TABLESPACE name [option …]`: a request to remove a tablespace.
 *
 * Mirrors PT_drop_tablespace (Sql_cmd_drop_tablespace) and the DROP_TABLESPACE command of st_alter_tablespace in MySQL 5.x. Rule: MYSQL-DROP-TABLESPACE-001. The options are NDB requests. The options are checked,
 * kept and written in order by MYSQL-STORAGE-OPTIONS-001. A tablespace is
 * no relation, so the statement neither declares nor resolves one.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-tablespace.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Removing a tablespace
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("drop tablespace ts engine ndb");
 *     [$statement->toString(), $statement->statement->name->value] // => ["DROP TABLESPACE ts ENGINE `ndb`", 'ts']
 */
final class DropTablespace implements Statement
{
    use Snapshot;

    /**
     * @var list<StorageOption> The options in written order
     */
    public readonly array $options;

    /**
     * @param Name $name The tablespace name
     * @param list<StorageOption> $options The options in written order
     */
    public function __construct(public readonly Name $name, array $options = [])
    {
        $this->options = (new StorageOptions())->checked($options, StorageOptions::DROP);
    }

    /**
     * Reports the options the server rejects while it parses the statement.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new StorageOptions())->derive($derivation, $this->options);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', 'TABLESPACE')->name($this->name, NameUse::Identifier);
        (new StorageOptions())->render($out, $this->options);
    }
}
