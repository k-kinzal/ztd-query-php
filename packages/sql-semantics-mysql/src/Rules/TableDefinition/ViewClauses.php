<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use Generator;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\SelectOptions;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDestination;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Reads view query clauses from their typed positions, without source SQL or parser nodes.
 *
 * Legacy servers refuse the first INTO before reading its targets. A variable in an earlier
 * expression takes precedence. Query modifiers before that boundary retain their conditions.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/create-view.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ViewClauses
{
    /**
     * Returns preceding-variable presence and modifier conditions at the first INTO, or null.
     *
     * @return array{bool, list<Deprecation|ParseFailure>}|null The structural reading boundary
     */
    public function before(Query $query, GrammarRelease $release): ?array
    {
        $variable = false;
        $notices = [];
        foreach ($this->nodes($query) as $node) {
            if ($node instanceof IntoDestination) {
                return [$variable, $notices];
            }
            $variable = $variable || $node instanceof UserVariable || $node instanceof SystemVariable || $node instanceof Parameter;
            if ($node instanceof Select) {
                array_push($notices, ...(new SelectOptions())->notices($node, $release));
            }
        }

        return null;
    }

    /**
     * Visits typed occurrences in clause order; INTO targets remain after their boundary.
     *
     * @return Generator<int, Node, void, void> Occurrences in their structural written order
     */
    public function nodes(Node $root): Generator
    {
        yield $root;
        $children = $root instanceof Select ? $this->select($root) : ($root instanceof QueryStatement ? [$root->query, $root->intoPosition === IntoPosition::AfterQuery ? $root->into : null, ...$root->locking, $root->intoPosition === IntoPosition::AfterLocking ? $root->into : null] : array_values(get_object_vars($root)));
        foreach ($children as $child) {
            foreach (is_array($child) ? $child : [$child] as $value) {
                if ($value instanceof Node) {
                    yield from $this->nodes($value);
                }
            }
        }
    }

    /**
     * Places a block's INTO between its actual preceding and following clauses.
     *
     * @return list<Node|null> Ordered clause operands
     */
    public function select(Select $select): array
    {
        return [...$select->items, $select->intoPosition === IntoPosition::AfterItems ? $select->into : null,
            $select->from, $select->where, $select->groupBy, $select->having, ...$select->windows,
            $select->qualify, ...$select->orderBy, $select->limit, $select->procedure,
            $select->intoPosition === IntoPosition::AfterQuery ? $select->into : null, ...$select->locking,
            $select->intoPosition === IntoPosition::AfterLocking ? $select->into : null, $select->late];
    }
}
