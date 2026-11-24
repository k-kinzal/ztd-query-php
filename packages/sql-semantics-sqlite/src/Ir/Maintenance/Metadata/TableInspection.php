<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * SQLite column, index or foreign-key information for a resolved table lookup.
 *
 * @visibility public
 * @example Inspecting a checked relation in the main database
 *     $main = new \SqlSemantics\Platform\Sqlite\Ir\Identifier('main');
 *     $table = new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget($main, new \SqlSemantics\Platform\Sqlite\Ir\Identifier('items'));
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableInspection(\SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableInspectionKind::ExtendedColumns, $table, $main))->toString() // => 'PRAGMA "main".table_xinfo("items")'
 */
final class TableInspection implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the operation, the selected relation or absence, and the explicit lookup scope.
     * @throws InvalidConstruction When the scope and selected relation contradict one another
     */
    public function __construct(
        public readonly TableInspectionKind $kind,
        public readonly RelationTarget|CatalogTable|MissingMetadataTable|null $target = null,
        public readonly ?Identifier $schema = null,
    ) {
        $aliases = new CatalogAliases();
        if ($target instanceof RelationTarget || $target instanceof CatalogTable) {
            Check::input($schema === null || $target->schema->key() === $schema->key(), 'The selected metadata table is outside the explicit schema.');
        }
        if ($target instanceof RelationTarget) {
            Check::input(!$aliases->isCatalogName($target->name), 'An internal SQLite schema table requires a catalog role.');
        }
        if ($target instanceof CatalogTable && $schema === null) {
            $database = $target->schema->key();
            Check::input($database === 'main' || $database === 'temp', 'An implicit metadata lookup cannot specify an attached schema catalog.');
        }
        if ($target instanceof MissingMetadataTable) {
            Check::input($aliases->permitsAbsence($kind, $target->name, $schema), 'This name cannot establish the absence of a native metadata table.');
        }
    }

    /**
     * Identifies the pinned SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the native lookup with explicit qualification exactly when requested.
     */
    public function toString(): string
    {
        $qualifier = $this->schema === null ? '' : $this->schema->quoted() . '.';
        $name = $this->target instanceof CatalogTable ? $this->target->name() : $this->target?->name;
        $argument = $name === null ? '' : '(' . $name->quoted() . ')';

        return 'PRAGMA ' . $qualifier . $this->kind->value . $argument;
    }
}
