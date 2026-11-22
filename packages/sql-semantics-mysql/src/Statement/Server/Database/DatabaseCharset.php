<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;

/**
 * `[DEFAULT] CHARACTER SET [=] charset`: the default character set of the database's tables.
 *
 * DEFAULT before the option and the equals sign are optional and not
 * written; CHARSET and CHARACTER SET are synonyms and CHARACTER SET is
 * written, because CHARSET right after ALTER DATABASE would be read as the
 * database name.
 * The name DEFAULT, accepted by MySQL 5.x, takes the server's character
 * set.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-database.html.
 *
 * @visibility SqlSemantics
 */
final class DatabaseCharset implements DatabaseOption
{
    use Snapshot;

    /**
     * @param CharsetName $charset The character set
     */
    public function __construct(public readonly CharsetName $charset)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('CHARACTER', 'SET')->node($this->charset);
    }
}
