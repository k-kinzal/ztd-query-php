<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Server\StorageOptions;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption;

/**
 * `ALTER UNDO TABLESPACE name SET {ACTIVE | INACTIVE} [option …]`: a request to activate or deactivate an undo tablespace (MySQL 8.0.14 and later).
 *
 * Mirrors PT_alter_undo_tablespace (Sql_cmd_alter_undo_tablespace). Rule: MYSQL-ALTER-UNDO-TABLESPACE-001. An inactive undo tablespace can be dropped. The options are checked,
 * kept and written in order by MYSQL-STORAGE-OPTIONS-001. A tablespace is
 * no relation, so the statement neither declares nor resolves one.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-tablespace.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterUndoTablespace implements Statement
{
    use Snapshot;

    /**
     * @var list<StorageOption> The options in written order
     */
    public readonly array $options;

    /**
     * @param Name $name The tablespace name
     * @param bool $active Whether ACTIVE (true) or INACTIVE (false) is written
     * @param list<StorageOption> $options The options in written order
     */
    public function __construct(public readonly Name $name, public readonly bool $active, array $options = [])
    {
        $this->options = (new StorageOptions())->checked($options, StorageOptions::UNDO_TABLESPACE);
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
        $out->keyword('ALTER', 'UNDO', 'TABLESPACE')->name($this->name, NameUse::Identifier)->keyword('SET', $this->active ? 'ACTIVE' : 'INACTIVE');
        (new StorageOptions())->render($out, $this->options);
    }
}
