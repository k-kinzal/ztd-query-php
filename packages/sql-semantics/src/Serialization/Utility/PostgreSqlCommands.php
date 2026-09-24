<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Utility;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Routes PostgreSQL database, tablespace, schema, session and data-transfer utility commands to their writers.
 * @visibility SqlSemantics
 */
final class PostgreSqlCommands
{
    /**
     * Returns null for statements outside these utility families.
     * @throws InvalidStructure
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return DatabaseCommands::write($statement) ?? TablespaceCommands::write($statement) ?? SchemaCommands::write($statement) ?? SystemSettings::write($statement) ?? SessionCommands::write($statement) ?? MaintenanceCommands::write($statement) ?? CopyCommands::write($statement);
    }
}
