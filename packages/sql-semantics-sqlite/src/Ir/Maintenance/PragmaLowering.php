<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance;

use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityAudit;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityAuditLowering;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityKind;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Inventory\InventoryKind;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Inventory\InventoryPragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\ForeignKeyAudit;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\ForeignKeyAuditLowering;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\IndexInspection;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\IndexInspectionKind;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\IndexMetadataLowering;
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
    public function lower(BoundStatement $operation, Pragma $statement): FlagPragma|InventoryPragma|IntegrityAudit|ForeignKeyAudit|IndexInspection|TableInspection|TableListing
    {
        $name = Comparison::AsciiInsensitive->fold($statement->name->name->value);
        $kind = TableInspectionKind::tryFrom($name);
        if ($kind !== null || $name === 'table_list') {
            return (new TableMetadataLowering())->lower($operation, $statement, $kind);
        }

        $integrity = IntegrityKind::tryFrom($name);
        if ($integrity !== null) {
            return (new IntegrityAuditLowering())->lower($operation, $statement, $integrity);
        }
        if ($name === 'foreign_key_check') {
            return (new ForeignKeyAuditLowering())->lower($operation, $statement);
        }
        $indexKind = IndexInspectionKind::tryFrom($name);
        if ($indexKind !== null) {
            return (new IndexMetadataLowering())->lower($operation, $statement, $indexKind);
        }
        $inventory = InventoryKind::tryFrom($name);
        if ($inventory !== null) {
            return new InventoryPragma($inventory, (new PragmaScope())->resolve($operation->context, $statement->name->schema));
        }

        return (new FlagPragmaLowering())->lower($operation, $statement);
    }
}
