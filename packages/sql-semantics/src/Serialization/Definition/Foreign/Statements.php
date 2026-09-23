<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Foreign;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Tree;

/**
 * Routes definitions of foreign connections, user mappings, and imported relations.
 * @visibility SqlSemantics
 */
final class Statements
{
    /**
     * Keeps each foreign object family's operand serializer separate.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return ForeignServers::write($statement)
            ?? ForeignRemovals::write($statement)
            ?? UserMappings::write($statement)
            ?? WrapperDeclarations::write($statement)
            ?? ForeignImports::write($statement);
    }
}
