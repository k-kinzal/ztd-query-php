<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Inventory;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * One read of a native SQLite connection registry.
 *
 * @visibility public
 * @example Listing the functions registered on the connection
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Inventory\InventoryPragma(\SqlSemantics\Platform\Sqlite\Ir\Maintenance\Inventory\InventoryKind::Functions))->toString() // => 'PRAGMA function_list'
 */
final class InventoryPragma implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the connection registry and the optional database requested for native access.
     */
    public function __construct(public readonly InventoryKind $kind, public readonly ?Identifier $schema = null)
    {
    }

    /**
     * Identifies the pinned SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits an inventory request with its explicit native access scope.
     */
    public function toString(): string
    {
        $qualifier = $this->schema === null ? '' : $this->schema->quoted() . '.';

        return 'PRAGMA ' . $qualifier . $this->kind->value;
    }
}
