<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Identity;

use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * An absolute table name absent in the namespace chosen by DROP TABLE.
 *
 * @visibility public
 * @example Quoting the name of an optional missing target
 *     $name = new \SqlSemantics\Platform\MySql\Ir\Schema\TableName(new \SqlSemantics\Platform\MySql\Ir\Identifier('app'), new \SqlSemantics\Platform\MySql\Ir\Identifier('missing'));
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\Identity\AbsentDropTarget($name))->quoted() // => '`app`.`missing`'
 */
final class AbsentDropTarget
{
    use Snapshot;

    /**
     * Retains the checked coordinate whose absence is permitted by the enclosing operation.
     */
    public function __construct(public readonly TableName $name)
    {
    }

    /**
     * Emits the exact absolute name.
     */
    public function quoted(): string
    {
        return $this->name->quoted();
    }
}
