<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Server;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Routes MySQL replication, binary log, flush, clone, component and instance commands to their binders.
 * @visibility SqlSemantics
 */
final class ServerCommands
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
            'purge' => BinaryLogs::purge($origin, $statement, $context),
            'install', 'install_stmt', 'uninstall' => Components::bind($origin, $statement, $context),
            'alter_instance_stmt' => Instances::bind($origin, $statement, $context),
            'clone_stmt' => Clones::bind($origin, $statement, $context),
            'change', 'change_replication_stmt' => Change\Changes::bind($origin, $statement, $context),
            'flush' => Flushes::bind($origin, $statement, $context),
            'slave', 'slave_start', 'start_replica_stmt', 'stop_replica_stmt', 'group_replication', 'group_replication_start' => Replicas::bind($origin, $statement, $context),
            default => null,
        };
    }
}
