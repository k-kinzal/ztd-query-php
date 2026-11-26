<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;

/**
 * Implements SQLite's builtin schema table name rules for metadata lookup.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CatalogAliases
{
    /**
     * Selects the native catalog when a known alias can reach it from the request scope.
     */
    public function resolve(Identifier $name, ?Identifier $schema): ?CatalogTable
    {
        $key = $name->key();
        if ($key === 'sqlite_schema' || $key === 'sqlite_master') {
            return new CatalogTable($schema ?? new Identifier('main'));
        }
        if (($key === 'sqlite_temp_schema' || $key === 'sqlite_temp_master') && ($schema === null || $schema->key() === 'temp')) {
            return new CatalogTable($schema ?? new Identifier('temp'));
        }

        return null;
    }

    /**
     * Identifies names reserved for a builtin SQLite schema table.
     */
    public function isCatalogName(Identifier $name): bool
    {
        return $this->resolve($name, null) !== null;
    }

    /**
     * Checks whether a missing name could still denote an internally supplied relation.
     */
    public function permitsAbsence(TableInspectionKind $kind, Identifier $name, ?Identifier $schema): bool
    {
        $key = $name->key();
        if ($this->resolve($name, $schema) !== null) {
            return false;
        }
        if (str_starts_with($key, 'sqlite_')) {
            return $key === 'sqlite_temp_schema' || $key === 'sqlite_temp_master';
        }
        if ($kind === TableInspectionKind::Columns || $kind === TableInspectionKind::ExtendedColumns) {
            return !str_starts_with($key, 'pragma_') && $key !== 'json_each' && $key !== 'json_tree';
        }

        return true;
    }
}
