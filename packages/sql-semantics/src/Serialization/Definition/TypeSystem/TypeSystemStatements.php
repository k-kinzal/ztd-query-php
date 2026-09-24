<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\TypeSystem;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Tree;

/**
 * Routes PostgreSQL type system definitions to their writers.
 * @visibility SqlSemantics
 */
final class TypeSystemStatements
{
    /**
     * Returns null for statements outside the type system family.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return Domains::write($statement)
            ?? TypeDefinitions::write($statement)
            ?? Casts::write($statement)
            ?? Operators::write($statement)
            ?? Locales::write($statement)
            ?? Aggregates::write($statement)
            ?? TextSearch::write($statement);
    }
}
