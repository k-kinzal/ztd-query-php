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
        return Trigger\EventTriggerCommands::write($statement)
            ?? View\Views::write($statement)
            ?? Ownership\OwnershipCommands::write($statement)
            ?? Routine\Alterations::write($statement)
            ?? Storage\Removals::write($statement)
            ?? Database\MySqlDatabases::write($statement)
            ?? Foreign\Statements::write($statement)
            ?? SpatialDefinitions::write($statement)
            ?? MySqlRemovals::write($statement)
            ?? Routines::write($statement)
            ?? match (true) {
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
