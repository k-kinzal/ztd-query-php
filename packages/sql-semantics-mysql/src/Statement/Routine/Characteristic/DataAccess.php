<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Characteristic;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The data access characteristic of a stored routine.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html.
 *
 * @visibility public
 * @example Reading the data access of a routine
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER PROCEDURE p READS SQL DATA');
 *     $alter->statement->characteristics[0]->level // => \SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\AccessLevel::ReadsSqlData
 */
final class DataAccess implements Characteristic
{
    use Snapshot;

    /**
     * @param AccessLevel $level The declared use of data
     */
    public function __construct(public readonly AccessLevel $level)
    {
    }

    /**
     * Writes the keywords of the level.
     */
    public function render(Output $out): void
    {
        match ($this->level) {
            AccessLevel::ContainsSql => $out->keyword('CONTAINS', 'SQL'),
            AccessLevel::NoSql => $out->keyword('NO', 'SQL'),
            AccessLevel::ReadsSqlData => $out->keyword('READS', 'SQL', 'DATA'),
            AccessLevel::ModifiesSqlData => $out->keyword('MODIFIES', 'SQL', 'DATA'),
        };
    }
}
