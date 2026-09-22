<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema\Alter;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;

/**
 * Finds column declarations introduced by ALTER, including MySQL's inline ADD form.
 * @visibility SqlSemantics
 */
final class AddedColumns
{
    /**
     * @return list<Node> Introduced column declarations, excluding MODIFY and CHANGE actions
     */
    public static function read(Node $source): array
    {
        $columns = Tree::outer($source, ['columnDef', 'column_def', 'columnname']);
        $actions = Tree::outer($source, ['alter_list_item']);
        return [...$columns, ...array_values(array_filter($actions, static fn (Node $action): bool => strtoupper($action->tokens()[0]->text ?? '') === 'ADD' && Tree::child($action, ['field_def']) !== null))];
    }
}
