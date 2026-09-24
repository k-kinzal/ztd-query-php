<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Procedural;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Loading;
use SqlSemantics\Model\Statement\Locking;
use SqlSemantics\Model\Statement\Procedural;

/**
 * Routes MySQL procedure calls, condition handling, handlers, help, table descriptions, bulk loads, instance locks and resource groups to their writers.
 * @visibility SqlSemantics
 */
final class ProceduralCommands
{
    /**
     * Returns null for statements outside this family.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof \SqlSemantics\Model\Statement\Cursor\Handler\OpenHandlerStatement,
            $statement instanceof \SqlSemantics\Model\Statement\Cursor\Handler\CloseHandlerStatement,
            $statement instanceof \SqlSemantics\Model\Statement\Cursor\Handler\ReadHandlerStatement,
            $statement instanceof \SqlSemantics\Model\Statement\Cursor\Handler\ReadHandlerIndexStatement,
            $statement instanceof \SqlSemantics\Model\Statement\Cursor\Handler\ReadHandlerKeyStatement => Handlers::write($statement),
            $statement instanceof Procedural\GetDiagnosticsStatement,
            $statement instanceof Procedural\GetConditionDiagnosticsStatement => Diagnostics::write($statement),
            $statement instanceof Procedural\SignalStatement,
            $statement instanceof Procedural\ResignalStatement => Conditions::write($statement),
            $statement instanceof \SqlSemantics\Model\Statement\Inspection\Schema\DescribeTableStatement => Descriptions::write($statement),
            $statement instanceof Procedural\CallStatement => Calls::write($statement),
            $statement instanceof Loading\LoadFileStatement,
            $statement instanceof Loading\BulkLoadStatement => Loads::write($statement),
            $statement instanceof Procedural\HelpStatement,
            $statement instanceof Loading\ImportTableStatement,
            $statement instanceof Locking\LockInstanceStatement,
            $statement instanceof Locking\UnlockInstanceStatement => Requests::write($statement),
            $statement instanceof \SqlSemantics\Model\Statement\Server\ResourceGroup\CreateResourceGroupStatement,
            $statement instanceof \SqlSemantics\Model\Statement\Server\ResourceGroup\AlterResourceGroupStatement,
            $statement instanceof \SqlSemantics\Model\Statement\Server\ResourceGroup\SetResourceGroupStatement => ResourceGroups::write($statement),
            default => null,
        };
    }
}
