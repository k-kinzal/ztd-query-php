<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Procedural;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Routes MySQL procedure calls, condition handling, handlers, help, table descriptions, bulk loads, instance locks and resource groups.
 * @visibility SqlSemantics
 */
final class ProceduralCommands
{
    /**
     * Returns null for statements outside this family.
     */
    public static function bind(Origin $origin, Node $statement, QueryContext $context): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::MySql) {
            return null;
        }
        return match ($statement->name) {
            'call', 'call_stmt' => Calls::bind($origin, $statement, $context),
            'describe', 'describe_stmt' => Descriptions::bind($origin, $statement, $context),
            'signal_stmt', 'resignal_stmt' => Conditions::bind($origin, $statement, $context),
            'get_diagnostics' => Diagnostics::bind($origin, $statement, $context),
            'handler', 'handler_stmt' => Handlers::bind($origin, $statement, $context),
            'help' => Requests::help($origin, $statement, $context),
            'load', 'load_stmt' => Loads::bind($origin, $statement, $context),
            'import_stmt' => Requests::import($origin, $statement),
            'lock', 'unlock' => Requests::instance($origin, $statement),
            'create_resource_group_stmt', 'alter_resource_group_stmt', 'set_resource_group_stmt' => ResourceGroups::bind($origin, $statement, $context),
            default => null,
        };
    }
}
