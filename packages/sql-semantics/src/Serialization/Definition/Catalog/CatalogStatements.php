<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Catalog;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Definition\Relation\ForeignTables;
use SqlSemantics\Serialization\Definition\Relation\RelationCommands;

/**
 * Routes PostgreSQL catalog, removal, relation, and foreign table statements to their writers.
 * @visibility SqlSemantics
 */
final class CatalogStatements
{
    /**
     * Returns null for statements outside these PostgreSQL families.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return CatalogCommands::write($statement)
            ?? CatalogRemovals::write($statement)
            ?? RelationCommands::write($statement)
            ?? ForeignTables::write($statement);
    }
}
