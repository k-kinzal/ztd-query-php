<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Checks that every window name a query block uses names a window of its WINDOW clause.
 *
 * Rule: MYSQL-WINDOW-NAME-001. A window name is written after OVER, as the
 * window a parenthesized window specification refines, or as the window a
 * named window of the WINDOW clause refines. It names a window defined in
 * the WINDOW clause of the same query block, compared without regard to
 * letter case; a subquery has a WINDOW clause of its own and is not
 * searched. A name no window of the block has is reported
 * (ER_WINDOW_NO_SUCH_WINDOW, raised while the server resolves the windows).
 * Terminates: the walk visits the finite parts of the block once and stops
 * at subqueries. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/window-functions-named-windows.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class WindowReferences
{
    /**
     * Reports each window name of the block that its WINDOW clause does not define.
     */
    public function check(Select $select, Derivation $derivation): void
    {
        $names = $derivation->context->columnNames;
        $defined = [];
        foreach ($select->windows as $window) {
            $defined[$names->fold($window->name->value)] = true;
        }
        $parts = [...$select->items, ...$select->orderBy, ...$select->windows, ...array_values(array_filter([$select->having, $select->qualify, $select->late]))];
        foreach ($this->names($parts) as $name) {
            if (!isset($defined[$names->fold($name->value)])) {
                $derivation->report(new Misuse(MisuseRule::UnknownWindow));
            }
        }
    }

    /**
     * Answers the window names used in parts of a query block, in written order, without entering subqueries.
     *
     * @param list<Node> $parts
     * @return list<Name>
     */
    public function names(array $parts): array
    {
        $found = [];
        $pending = array_reverse($parts);
        while ($pending !== []) {
            $node = array_pop($pending);
            if ($node instanceof Query) {
                continue;
            }
            $reference = match (true) {
                $node instanceof WindowFunction, $node instanceof Aggregate, $node instanceof GroupConcat, $node instanceof JsonObjectAggregate => $node->over,
                $node instanceof WindowSpec => $node->base,
                default => null,
            };
            if ($reference instanceof Name) {
                $found[] = $reference;
            }
            $children = [];
            foreach (get_object_vars($node) as $value) {
                foreach (is_array($value) ? $value : [$value] as $member) {
                    if ($member instanceof Node) {
                        $children[] = $member;
                    }
                }
            }
            array_push($pending, ...array_reverse($children));
        }

        return $found;
    }
}
