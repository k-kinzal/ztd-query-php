<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Trigger;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger as Statement;
use SqlSemantics\Serialization\Definition\Ownership\OwnershipCommands;

/**
 * Writes each event-trigger alteration from its own required operand types.
 * @visibility SqlSemantics
 */
final class EventTriggerCommands
{
    /**
     * Uses identifier boundaries for trigger names and typed syntax for policies and roles.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if ($statement instanceof Statement\DropEventTriggersStatement) {
            return new Tree('drop-event-triggers', [
                Build::keyword('DROP EVENT TRIGGER' . ($statement->ifExists ? ' IF EXISTS' : '')),
                Build::separated(array_map(static fn (string $name): Tree => Build::identifier([$name], Dialect::PostgreSql), $statement->names)),
                ...($statement->behavior->value === '' ? [] : [Build::keyword($statement->behavior->value)]),
            ]);
        }
        if (!$statement instanceof Statement\AlterEventTriggerFiringStatement && !$statement instanceof Statement\RenameEventTriggerStatement && !$statement instanceof Statement\ChangeEventTriggerOwnerStatement) {
            return null;
        }
        $change = match (true) {
            $statement instanceof Statement\AlterEventTriggerFiringStatement => [Build::keyword($statement->firing->value)],
            $statement instanceof Statement\RenameEventTriggerStatement => [Build::keyword('RENAME TO'), Build::identifier([$statement->newName], Dialect::PostgreSql)],
            $statement instanceof Statement\ChangeEventTriggerOwnerStatement => [Build::keyword('OWNER TO'), OwnershipCommands::role($statement->newOwner)],
        };
        return new Tree('alter-event-trigger', [Build::keyword('ALTER EVENT TRIGGER'), Build::identifier([$statement->name], Dialect::PostgreSql), ...$change]);
    }
}
