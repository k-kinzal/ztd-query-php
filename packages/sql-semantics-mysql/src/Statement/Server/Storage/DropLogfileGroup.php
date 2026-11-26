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
 * `DROP LOGFILE GROUP name [option …]`: a request to remove an NDB log file group.
 *
 * Mirrors PT_drop_logfile_group and the DROP_LOGFILE_GROUP command of st_alter_tablespace in MySQL 5.x. Rule: MYSQL-DROP-LOGFILE-GROUP-001.  The options are checked,
 * kept and written in order by MYSQL-STORAGE-OPTIONS-001. A log file group is
 * no relation, so the statement neither declares nor resolves one.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-logfile-group.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class DropLogfileGroup implements Statement
{
    use Snapshot;

    /**
     * @var list<StorageOption> The options in written order
     */
    public readonly array $options;

    /**
     * @param Name $name The log file group name
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
        $out->keyword('DROP', 'LOGFILE', 'GROUP')->name($this->name, NameUse::Identifier);
        (new StorageOptions())->render($out, $this->options);
    }
}
