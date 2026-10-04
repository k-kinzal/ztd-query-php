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
 * `CREATE LOGFILE GROUP name ADD {UNDOFILE | REDOFILE} 'file' [option …]`: a request to create an NDB log file group.
 *
 * Mirrors PT_create_logfile_group and the CREATE_LOGFILE_GROUP command of st_alter_tablespace in MySQL 5.x. Rule: MYSQL-CREATE-LOGFILE-GROUP-001. REDOFILE is accepted by the MySQL 5.x grammar only. The options are checked,
 * kept and written in order by MYSQL-STORAGE-OPTIONS-001. A log file group is
 * no relation, so the statement neither declares nor resolves one.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-logfile-group.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Creating a log file group
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("create logfile group lg add undofile 'u.log' undo_buffer_size = 8M engine = ndb");
 *     [$statement->toString(), $statement->statement->file->file->value] // => ["CREATE LOGFILE GROUP lg ADD UNDOFILE 'u.log' UNDO_BUFFER_SIZE `8M` ENGINE `ndb`", 'u.log']
 */
final class CreateLogfileGroup implements Statement
{
    use Snapshot;

    /**
     * @var list<StorageOption> The options in written order
     */
    public readonly array $options;

    /**
     * @param Name $name The log file group name
     * @param LogFile $file The log file
     * @param list<StorageOption> $options The options in written order
     */
    public function __construct(public readonly Name $name, public readonly LogFile $file, array $options = [])
    {
        $this->options = (new StorageOptions())->checked($options, StorageOptions::LOGFILE_GROUP);
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
        $out->keyword('CREATE', 'LOGFILE', 'GROUP')->name($this->name, NameUse::Identifier)->keyword('ADD')->node($this->file);
        (new StorageOptions())->render($out, $this->options);
    }
}
