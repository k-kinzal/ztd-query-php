<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Session;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Routes session operations, execution plans and named schema commands.
 * @visibility SqlSemantics
 */
final class ExecutionBinder
{
    /**
     * Returns the first operation whose grammar belongs to an explicit command family.
     */
    public static function bind(Origin $origin, Node $statement, QueryContext $context): ?BoundStatement
    {
        $scope = new Scope($context->tables->identifiers, queries: $context);
        return Statement\Definition\MySqlRemovals::bind($origin, $statement, $context)
            ?? Statement\Routine\DropRoutines::bind($origin, $statement, $context)
            ?? Statement\Maintenance\IndexCaches::bind($origin, $statement, $context)
            ?? Statement\Maintenance\MySqlTables::bind($origin, $statement, $context)
            ?? Statement\Maintenance\TruncateBinder::bind($origin, $statement, $context)
            ?? TableLockBinder::bind($origin, $statement, $context)
            ?? Statement\Inspection\ServerInspection::bind($origin, $statement)
            ?? DoBinder::bind($origin, $statement, $scope)
            ?? SessionBinder::bind($origin, $statement, $scope)
            ?? Statement\Prepared\PreparedBinder::bind($origin, $statement, $context)
            ?? Statement\Cursor\CursorBinder::bind($origin, $statement, $context)
            ?? Statement\Plan\ExplainBinder::bind($origin, $statement, $context)
            ?? Statement\Transaction\XaBinder::bind($origin, $statement)
            ?? (new Statement\TransactionBinder())->bind($origin, $statement, $scope)
            ?? (new Statement\MaintenanceBinder())->bind($origin, $statement, $scope)
            ?? Statement\ObjectBinder::bind($origin, $statement, $context);
    }
}
