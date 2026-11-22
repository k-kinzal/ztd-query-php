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
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\DatafileAction;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption;

/**
 * `ALTER TABLESPACE name {ADD | DROP | CHANGE} DATAFILE 'file' [option …]`: a request to add, remove or resize a data file of a tablespace.
 *
 * Mirrors PT_alter_tablespace_add_datafile, PT_alter_tablespace_drop_datafile and the ALTER_TABLESPACE commands of st_alter_tablespace in MySQL 5.x. Rule: MYSQL-ALTER-TABLESPACE-DATAFILE-001. ADD and DROP are NDB requests; CHANGE (MySQL 5.x) takes size options only and at least one. The options are checked,
 * kept and written in order by MYSQL-STORAGE-OPTIONS-001. A tablespace is
 * no relation, so the statement neither declares nor resolves one.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-tablespace.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterTablespaceDatafile implements Statement
{
    use Snapshot;

    /**
     * @var list<StorageOption> The options in written order
     */
    public readonly array $options;

    /**
     * @param Name $name The tablespace name
     * @param DatafileAction $action ADD, DROP or CHANGE
     * @param Text $datafile The data file
     * @param list<StorageOption> $options The options in written order
     */
    public function __construct(public readonly Name $name, public readonly DatafileAction $action, public readonly Text $datafile, array $options = [])
    {
        $this->options = (new StorageOptions())->checked($options, $action === DatafileAction::Change ? StorageOptions::CHANGE_DATAFILE : StorageOptions::ALTER_TABLESPACE, $action === DatafileAction::Change ? 1 : 0);
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
        $out->keyword('ALTER', 'TABLESPACE')->name($this->name, NameUse::Identifier)->keyword($this->action->value, 'DATAFILE')->node($this->datafile);
        (new StorageOptions())->render($out, $this->options);
    }
}
