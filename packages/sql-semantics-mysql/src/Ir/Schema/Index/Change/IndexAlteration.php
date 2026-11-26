<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Change;

use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\IndexRename;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A checked physical removal or rename within an owning ALTER TABLE.
 * @visibility public
 * @example Emitting a rename action
 *     $name = \SqlSemantics\Platform\MySql\Ir\Identifier::class;
 *     $move = new \SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\IndexRename(new $name('before'), new $name('after'));
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\Index\Change\IndexAlteration($move))->toString() // => 'RENAME INDEX `before` TO `after`'
 */
final class IndexAlteration
{
    use Snapshot;

    /**
     * Stores the two closed action roles.
     */
    public function __construct(public readonly IndexIdentity|IndexRename $operation)
    {
    }

    /**
     * Renders only this action as part of the owning ALTER TABLE.
     */
    public function toString(): string
    {
        return $this->operation instanceof IndexIdentity
            ? 'DROP INDEX ' . $this->operation->name->quoted()
            : $this->operation->toString();
    }
}
