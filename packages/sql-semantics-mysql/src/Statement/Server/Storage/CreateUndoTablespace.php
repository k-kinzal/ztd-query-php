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
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption;

/**
 * `CREATE UNDO TABLESPACE name ADD DATAFILE 'file' [option …]`: a request to create an undo tablespace (MySQL 8.0.14 and later).
 *
 * Mirrors PT_create_undo_tablespace (Sql_cmd_create_undo_tablespace). Rule: MYSQL-CREATE-UNDO-TABLESPACE-001. The data file name ends in .ibu. The options are checked,
 * kept and written in order by MYSQL-STORAGE-OPTIONS-001. A tablespace is
 * no relation, so the statement neither declares nor resolves one.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CreateUndoTablespace implements Statement
{
    use Snapshot;

    /**
     * @var list<StorageOption> The options in written order
     */
    public readonly array $options;

    /**
     * @param Name $name The tablespace name
     * @param Text $datafile The data file
     * @param list<StorageOption> $options The options in written order
     */
    public function __construct(public readonly Name $name, public readonly Text $datafile, array $options = [])
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
        $out->keyword('CREATE', 'UNDO', 'TABLESPACE')->name($this->name, NameUse::Identifier)->keyword('ADD', 'DATAFILE')->node($this->datafile);
        (new StorageOptions())->render($out, $this->options);
    }
}
