<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Inspection;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Query\Inspection\ProcessQueryText;
use SqlSemantics\Model\Statement\Inspection as Statement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Classifies server inspection commands with fixed metadata result roles.
 * @visibility SqlSemantics
 */
final class ServerInspection
{
    /**
     * Normalizes synonymous spellings to the same semantic request.
     */
    public static function bind(Origin $origin, Node $node): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::MySql) {
            return null;
        }
        return match (strtoupper(Tree::text($node))) {
            'SHOW ENGINES', 'SHOW STORAGE ENGINES' => new Statement\ShowEnginesStatement($origin),
            'SHOW PLUGINS' => new Statement\ShowPluginsStatement($origin),
            'SHOW PRIVILEGES' => new Statement\ShowPrivilegesStatement($origin),
            'SHOW PROCESSLIST' => new Statement\ShowProcessesStatement($origin),
            'SHOW FULL PROCESSLIST' => new Statement\ShowProcessesStatement($origin, ProcessQueryText::Complete),
            default => null,
        };
    }
}
