<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * `[DEFAULT] ENCRYPTION [=] {'Y' | 'N'}`: the default encryption of the database's tables (MySQL 8.0.16 and later).
 *
 * DEFAULT before the option is optional and always written, because
 * ENCRYPTION right after ALTER DATABASE would be read as the database name;
 * the equals sign is optional and not written. The server checks the value
 * when it executes the statement.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-database.html.
 *
 * @visibility SqlSemantics
 */
final class DatabaseEncryption implements DatabaseOption
{
    use Snapshot;

    /**
     * @param Text $encryption The value, 'Y' or 'N' for the server
     */
    public function __construct(public readonly Text $encryption)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('DEFAULT', 'ENCRYPTION')->node($this->encryption);
    }
}
