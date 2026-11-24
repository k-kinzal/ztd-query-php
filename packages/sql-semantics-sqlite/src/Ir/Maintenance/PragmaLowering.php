<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance;

use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Inventory\InventoryKind;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Inventory\InventoryPragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableInspection;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableInspectionKind;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableListing;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableMetadataLowering;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Pragma;

/**
 * Publishes a pragma through its checked native operation family.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class PragmaLowering
{
    /**
     * Dispatches a known SQLite operation by its exact native identity.
     * @throws InvalidConstruction When the selected pragma family has no complete representation
     */
    public function lower(BoundStatement $operation, Pragma $statement): FlagPragma|InventoryPragma|TableInspection|TableListing
    {
        $name = Comparison::AsciiInsensitive->fold($statement->name->name->value);
        $kind = TableInspectionKind::tryFrom($name);
        if ($kind !== null || $name === 'table_list') {
            return (new TableMetadataLowering())->lower($operation, $statement, $kind);
        }

        $inventory = InventoryKind::tryFrom($name);
        if ($inventory !== null) {
            return new InventoryPragma($inventory, (new PragmaScope())->resolve($operation->context, $statement->name->schema));
        }

        return (new FlagPragmaLowering())->lower($operation, $statement);
    }
}
