<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database;

use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * `[DEFAULT] COLLATE [=] collation`: the default collation of the database's tables.
 *
 * DEFAULT before the option and the equals sign are optional and not
 * written. The name DEFAULT, accepted by MySQL 5.x, takes the collation of
 * the character set.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-database.html.
 *
 * @visibility public
 * @example Reading the collation
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER DATABASE d COLLATE utf8mb4_bin');
 *     $alter->statement->options[0]->collation->name?->value // => 'utf8mb4_bin'
 */
final class DatabaseCollation implements DatabaseOption
{
    use Snapshot;

    /**
     * @param CollationName $collation The collation
     */
    public function __construct(public readonly CollationName $collation)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('COLLATE')->node($this->collation);
    }
}
