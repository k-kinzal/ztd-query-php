<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Inspection;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Tree;

/**
 * Routes schema, definition, engine, and profile inspections to their writers.
 * @visibility SqlSemantics
 */
final class SchemaInspections
{
    /**
     * Returns null for statements outside this inspection family.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return Listings::write($statement) ?? Definitions::write($statement) ?? Reports::write($statement);
    }
}
