<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * An absolute persistent constraint in the native database namespace.
 * @visibility public
 * @example Reading a resolved catalog constraint symbol
 *     $name = \SqlSemantics\Platform\MySql\Ir\Identifier::class;
 *     $table = new \SqlSemantics\Platform\MySql\Ir\Schema\TableName(new $name('app'), new $name('child'));
 *     $owner = new \SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity($table, \SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind::BaseTable);
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyConstraintIdentity($owner, new $name('Parent_Link')))->symbol->value // => 'Parent_Link'
 */
final class ForeignKeyConstraintIdentity
{
    use Snapshot;

    /**
     * Checks the symbol in its absolute owning table.
     */
    public function __construct(public readonly TableIdentity $owner, public readonly Identifier $symbol)
    {
        Check::input($owner->kind === ExternalRelationKind::BaseTable, 'A native constraint symbol must belong to a persistent table.');
        Check::input(preg_match('/\A.{0,64}\z/us', $symbol->value) === 1, 'A native foreign-key name has at most 64 characters.');
    }
}
