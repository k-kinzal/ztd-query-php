<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement;
use SqlSemantics\Serialization\Declarations;

/**
 * Routes declarations and schema changes by their concrete semantic types.
 * @visibility SqlSemantics
 */
final class Statements
{
    /**
     * Returns null for query, data mutation, configuration, and execution operations.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        $writers = [
            Trigger\ChangeReactions::write(...),
            View\Views::write(...),
            Ownership\OwnershipCommands::write(...),
            Role\RoleCommands::write(...),
            Routine\Alterations::write(...),
            Program\StoredPrograms::write(...),
            Storage\Removals::write(...),
            MySqlObject\ObjectDefinitions::write(...),
            Database\MySqlDatabases::write(...),
            MySqlTable\MySqlTables::write(...),
            Table\PostgreSqlTables::write(...),
            Foreign\Statements::write(...),
            SpatialDefinitions::write(...),
            MySqlRemovals::write(...),
            Account\AccountCommands::write(...),
            Routines::write(...),
            Catalog\CatalogStatements::write(...),
            TypeSystem\TypeSystemStatements::write(...),
            Extensibility\ExtensibilityStatements::write(...),
            self::schemaCommands(...),
        ];
        foreach ($writers as $writer) {
            $tree = $writer($statement);
            if ($tree !== null) {
                return $tree;
            }
        }
        return null;
    }

    /**
     * Writes the generic table, index, trigger, and view commands shared by every dialect.
     */
    public static function schemaCommands(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Statement\Definition\DropTableIndexStatement,
            $statement instanceof Statement\Definition\DropIndexConcurrentlyStatement,
            $statement instanceof Statement\Definition\DropTableTriggerStatement => OwnedDrops::write($statement),
            $statement instanceof Statement\Definition\CreateVirtualTableStatement => VirtualTables::write($statement),
            $statement instanceof Statement\Definition\CreateSqliteTriggerStatement => Triggers::writeSqlite($statement),
            $statement instanceof Statement\Definition\DropTableStatement,
            $statement instanceof Statement\Definition\DropViewStatement,
            $statement instanceof Statement\Definition\DropIndexStatement,
            $statement instanceof Statement\Definition\DropTriggerStatement,
            $statement instanceof Statement\Definition\RenameTableStatement,
            $statement instanceof Statement\Definition\RenameColumnStatement,
            $statement instanceof Statement\Definition\DropColumnStatement,
            $statement instanceof Statement\Definition\AddColumnStatement,
            $statement instanceof Statement\Definition\CreateTableAsStatement => SchemaCommands::write($statement),
            $statement instanceof Statement\Table\CreateTableLikeStatement => Declarations::like($statement),
            $statement instanceof Statement\CreateTableStatement => Declarations::table($statement),
            $statement instanceof Statement\CreateIndexStatement => Declarations::index($statement),
            default => null,
        };
    }
}
