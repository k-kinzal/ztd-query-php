<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetItem;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;

/**
 * Prepares one reached stored assignment without reading SQL or inventing a scalar.
 *
 * @visibility MySqlMemory
 */
final class AssignmentUse
{
    /**
     * Raises the dependency or width condition of the assigned operand.
     *
     * @throws \MySqlMemory\Error\SqlError Always, after binding the reached operand
     */
    public function raise(DeferredAssignment $item, Session $session): never
    {
        \MySqlMemory\Session\State\StatementCounters::query($session);
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_set_option');
        $session->diagnostics->clear();
        (new RowUse())->raise($item->value, $session);
    }

    /**
     * Makes a preceding written scope explicit when a stored list executes item by item.
     */
    public function inherited(SetItem $item, ?VariableScope $scope): SetItem
    {
        if (!$item instanceof NameAssignment || $item->scope !== null || $scope === null) {
            return $item;
        }
        $value = $item->value instanceof BareName ? new BareName($item->value->word, $item->value->qualifier, true, $item->value->parentheses) : $item->value;
        return new NameAssignment($item->name, $value, $scope, $item->qualifier, operator: $item->operator);
    }
}
