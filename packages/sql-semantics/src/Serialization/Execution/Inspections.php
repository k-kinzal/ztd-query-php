<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Execution;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Inspection as Statement;

/**
 * Writes server-inspection requests from their concrete operation and result-detail policy.
 * @visibility SqlSemantics
 */
final class Inspections
{
    /**
     * Routes only classified metadata inspection forms.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Statement\ShowEnginesStatement => Build::keyword('SHOW ENGINES'),
            $statement instanceof Statement\ShowPluginsStatement => Build::keyword('SHOW PLUGINS'),
            $statement instanceof Statement\ShowPrivilegesStatement => Build::keyword('SHOW PRIVILEGES'),
            $statement instanceof Statement\ShowProcessesStatement => new Tree('show_processes', [Build::keyword('SHOW'), Build::keyword($statement->queryText->value), Build::keyword('PROCESSLIST')]),
            default => null,
        };
    }
}
