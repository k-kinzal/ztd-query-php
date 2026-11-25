<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\DropDependency;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A checked native child-to-parent relation supplied to a table removal.
 * @visibility public
 * @example Supplying a persistent reference
 *     $name = \SqlSemantics\Platform\MySql\Ir\Identifier::class;
 *     $table = \SqlSemantics\Platform\MySql\Ir\Schema\TableName::class;
 *     $identity = \SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity::class;
 *     $persistent = \SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind::BaseTable;
 *     $child = new $identity(new $table(new $name('app'), new $name('comments')), $persistent);
 *     $parent = new $identity(new $table(new $name('app'), new $name('posts')), $persistent);
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\DropDependency\TableDropReference($child, $parent))->child->name->table->value // => 'comments'
 */
final class TableDropReference
{
    use Snapshot;

    /**
     * Holds only the two resolved persistent absolute identifiers.
     */
    public function __construct(public readonly TableIdentity $child, public readonly TableIdentity $parent)
    {
        Check::input(
            $child->kind === ExternalRelationKind::BaseTable && $parent->kind === ExternalRelationKind::BaseTable,
            'A native foreign key relates two persistent tables.'
        );
    }
}
