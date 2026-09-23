<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Routes definition requests by their semantic object families.
 * @visibility SqlSemantics
 */
final class DefinitionBinder
{
    /**
     * Returns null for statements outside these named definition families.
     */
    public static function bind(Origin $origin, Node $statement, QueryContext $context): ?BoundStatement
    {
        return Trigger\EventTriggerBinder::bind($origin, $statement, $context)
            ?? Ownership\OwnershipCommands::bind($origin, $statement)
            ?? \SqlSemantics\Binding\Statement\Routine\AlterRoutines::bind($origin, $statement, $context)
            ?? Storage\Removals::bind($origin, $statement, $context)
            ?? Database\MySqlDatabases::bind($origin, $statement, $context)
            ?? ForeignServers::bind($origin, $statement, $context)
            ?? ForeignRemovals::bind($origin, $statement, $context)
            ?? UserMappings::bind($origin, $statement, $context)
            ?? WrapperDeclarations::bind($origin, $statement, $context)
            ?? ForeignImports::bind($origin, $statement, $context)
            ?? SpatialDefinitions::bind($origin, $statement)
            ?? MySqlRemovals::bind($origin, $statement, $context)
            ?? \SqlSemantics\Binding\Statement\Routine\DropRoutines::bind($origin, $statement, $context);
    }
}
