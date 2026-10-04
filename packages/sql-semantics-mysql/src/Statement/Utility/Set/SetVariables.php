<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Set;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SET with a list of assignments: variables, the connection character sets, and in MySQL 5.6 a password.
 *
 * Rule: MYSQL-SET-001. The assignments run in the order written. A scope
 * keyword written before a variable name (`SET GLOBAL a = 1`) also applies
 * to every later assignment that names a variable without a scope of its
 * own (`b` in `SET GLOBAL a = 1, b = 2`); a scope written inside `@@`
 * applies to its own variable only, and `@x` assignments have no scope.
 * scopeOf() answers that inherited scope. Each item derives its own parts
 * (MYSQL-SET-ITEM-001); the statement returns no rows and provides no
 * declaration. Terminates: one pass over the items.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-variable.html
 * ("the most recent GLOBAL, PERSIST, PERSIST_ONLY, or SESSION modifier in
 * the statement is used for following assignments that have no modifier
 * specified"). Status: Implemented.
 *
 * @visibility public
 * @example Reading the scope an assignment inherits
 *     $set = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SET GLOBAL a = 1, b = ON');
 *     [$set->statement->scopeOf(1), $set->toString()] // => [\SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Global, 'SET GLOBAL a = 1, b = ON']
 */
final class SetVariables implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<SetItem> The assignments in order
     */
    public readonly array $items;

    /**
     * @param list<SetItem> $items The assignments in order; at least one
     */
    public function __construct(array $items)
    {
        $this->items = Check::listOf($items, SetItem::class, 'SET assigns at least one item.', 1);
    }

    /**
     * Answers the scope a variable assignment at a position assigns in: its own, or the last one written before it by a name assignment.
     *
     * Null means that no scope applies: none is written, so a system
     * variable is assigned in the session, or the item is no system
     * variable assignment.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the position is not one of the items
     */
    public function scopeOf(int $position): ?VariableScope
    {
        Check::input(isset($this->items[$position]), 'The position is one of the items.');
        $item = $this->items[$position];
        if ($item instanceof SystemAssignment) {
            return $item->variable->scope;
        }
        if (!$item instanceof NameAssignment) {
            return null;
        }
        for ($index = $position; $index >= 0; $index--) {
            $earlier = $this->items[$index];
            if ($earlier instanceof NameAssignment && $earlier->scope !== null) {
                return $earlier->scope;
            }
        }

        return null;
    }

    /**
     * Derives every assignment in order; the statement returns no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        foreach ($this->items as $item) {
            $item->deriveItem($derivation);
        }
    }

    /**
     * Writes SET and the assignments separated by commas.
     */
    public function render(Output $out): void
    {
        $out->keyword('SET')->list($this->items);
    }
}
