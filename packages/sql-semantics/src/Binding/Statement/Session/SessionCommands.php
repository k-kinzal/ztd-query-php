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
 * Routes session control, prepared statements, cursors, plans, transactions and server commands.
 * @visibility SqlSemantics
 */
final class SessionCommands
{
    /**
     * Returns the first session or server operation matching the statement, or null for other families.
     */
    public static function bind(Origin $origin, Node $statement, QueryContext $context): ?BoundStatement
    {
        $scope = new Scope($context->tables->identifiers, queries: $context);
        $binders = [
            static fn (): ?BoundStatement => DoBinder::bind($origin, $statement, $scope),
            static fn (): ?BoundStatement => SessionBinder::bind($origin, $statement, $scope),
            static fn (): ?BoundStatement => Statement\Prepared\PreparedBinder::bind($origin, $statement, $context),
            static fn (): ?BoundStatement => Statement\Cursor\CursorBinder::bind($origin, $statement, $context),
            static fn (): ?BoundStatement => Statement\Plan\ExplainBinder::bind($origin, $statement, $context),
            static fn (): ?BoundStatement => Statement\Transaction\XaBinder::bind($origin, $statement),
            static fn (): ?BoundStatement => (new Statement\TransactionBinder())->bind($origin, $statement, $scope),
            static fn (): ?BoundStatement => (new Statement\MaintenanceBinder())->bind($origin, $statement, $scope),
            static fn (): ?BoundStatement => Statement\Server\ServerCommands::bind($origin, $statement, $context),
            static fn (): ?BoundStatement => Statement\ObjectBinder::bind($origin, $statement, $context),
        ];
        foreach ($binders as $binder) {
            $bound = $binder();
            if ($bound !== null) {
                return $bound;
            }
        }
        return null;
    }
}
