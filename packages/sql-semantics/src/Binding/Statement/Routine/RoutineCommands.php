<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Routes routine and stored program definitions: MySQL routine alterations, routine removals, and MySQL stored programs.
 * @visibility SqlSemantics
 */
final class RoutineCommands
{
    /**
     * Returns null for statements outside the routine families.
     */
    public static function bind(Origin $origin, Node $statement, QueryContext $context): ?BoundStatement
    {
        return AlterRoutines::bind($origin, $statement, $context)
            ?? DropRoutines::bind($origin, $statement, $context)
            ?? Stored\StoredPrograms::bind($origin, $statement, $context);
    }
}
