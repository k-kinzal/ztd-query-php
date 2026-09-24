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
        foreach ([ForeignServers::write(...), ForeignRemovals::write(...), UserMappings::write(...), WrapperDeclarations::write(...), ForeignImports::write(...)] as $writer) {
            $tree = $writer($statement);
            if ($tree !== null) {
                return $tree;
            }
        }
        return null;
    }
}
