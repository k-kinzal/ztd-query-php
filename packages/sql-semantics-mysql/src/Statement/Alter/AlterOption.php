<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Statement\Node;

/**
 * An ALGORITHM or LOCK option of an online data definition statement.
 *
 * ALTER TABLE, CREATE INDEX and DROP INDEX hold them; the statement that
 * holds an option derives it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-performance.
 */
interface AlterOption extends Node
{
    /**
     * Reports a value the server of the release does not know.
     */
    public function deriveOption(Derivation $derivation): void;
}
