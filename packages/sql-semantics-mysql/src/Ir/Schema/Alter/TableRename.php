<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Alter;

use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * One ordered table move with a current coordinate and an externally resolved origin.
 *
 * @visibility public
 * @example Reading the original identity behind an intermediate name
 *     $database = new \SqlSemantics\Platform\MySql\Ir\Identifier('shop');
 *     $initial = new \SqlSemantics\Platform\MySql\Ir\Schema\TableName($database, new \SqlSemantics\Platform\MySql\Ir\Identifier('entries'));
 *     $current = new \SqlSemantics\Platform\MySql\Ir\Schema\TableName($database, new \SqlSemantics\Platform\MySql\Ir\Identifier('staged'));
 *     $final = new \SqlSemantics\Platform\MySql\Ir\Schema\TableName($database, new \SqlSemantics\Platform\MySql\Ir\Identifier('archive'));
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\Alter\TableRename($current, $final, $initial))->origin->table->value // => 'entries'
 */
final class TableRename
{
    use Snapshot;

    /**
     * Fixes the three identity roles of the move.
     */
    public function __construct(
        public readonly TableName $from,
        public readonly TableName $to,
        public readonly TableName $origin,
    ) {
        Check::input($from->quoted() !== $to->quoted(), 'A table rename requires distinct current and destination identities.');
    }

    /**
     * Emits the current and destination name at this step of the native operation.
     */
    public function toString(): string
    {
        return $this->from->quoted() . ' TO ' . $this->to->quoted();
    }
}
