<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Identity;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A resolved absolute table in the persistent or session-local namespace.
 *
 * @visibility public
 * @example Reading the namespace of a session-owned table
 *     $name = new \SqlSemantics\Platform\MySql\Ir\Schema\TableName(new \SqlSemantics\Platform\MySql\Ir\Identifier('app'), new \SqlSemantics\Platform\MySql\Ir\Identifier('temporary_report'));
 *     $target = new \SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity($name, \SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind::TemporaryTable);
 *     $target->kind->value // => 'temporary-table'
 */
final class TableIdentity
{
    use Snapshot;

    /**
     * Establishes the native table namespace at these checked coordinates.
     */
    public function __construct(
        public readonly TableName $name,
        public readonly ExternalRelationKind $kind,
    ) {
        Check::input($kind->isTable(), 'A resolved table identity requires a persistent or temporary table.');
    }

    /**
     * Emits the coordinates for native resolution in the corresponding connection.
     */
    public function quoted(): string
    {
        return $this->name->quoted();
    }
}
