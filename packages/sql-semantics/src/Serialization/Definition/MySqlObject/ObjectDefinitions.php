<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\MySqlObject;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Tree;

/**
 * Routes MySQL tablespace, logfile group, server, view alteration, and loadable function definitions to their writers.
 * @visibility SqlSemantics
 */
final class ObjectDefinitions
{
    /**
     * Returns null for statements outside these MySQL object definitions.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return StorageDefinitions::write($statement) ?? ServerDefinitions::write($statement);
    }
}
