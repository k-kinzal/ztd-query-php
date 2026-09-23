<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Storage;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\MySql\Storage as Statement;

/**
 * Writes storage deletion operands, with no wait payload on undo-tablespace removal.
 * @visibility SqlSemantics
 */
final class Removals
{
    /**
     * Builds SQL from the concrete operation and its independently quoted identities.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if (!$statement instanceof Statement\DropTablespaceStatement && !$statement instanceof Statement\DropLogfileGroupStatement && !$statement instanceof Statement\DropUndoTablespaceStatement) {
            return null;
        }
        $operation = match (true) {
            $statement instanceof Statement\DropTablespaceStatement => 'DROP TABLESPACE',
            $statement instanceof Statement\DropLogfileGroupStatement => 'DROP LOGFILE GROUP',
            $statement instanceof Statement\DropUndoTablespaceStatement => 'DROP UNDO TABLESPACE',
        };
        return new Tree('storage-removal', [
            Build::keyword($operation), Build::identifier([$statement->name], Dialect::MySql),
            ...($statement->engine === null ? [] : [Build::keyword('ENGINE'), Build::identifier([$statement->engine], Dialect::MySql)]),
            ...($statement instanceof Statement\DropUndoTablespaceStatement ? [] : [Build::keyword($statement->waiting->value)]),
        ]);
    }
}
