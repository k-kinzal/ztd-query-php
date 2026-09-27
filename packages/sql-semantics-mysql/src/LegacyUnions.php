<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use SqlSemantics\Core\CompositionException;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Model\MySql\Role\SelectInitForm;
use SqlSemantics\Statement\Traversal;
use SqlSemantics\Statement\Writer;

/**
 * Composes `UNION ALL` in the 5.6 and 5.7 query grammars, where a union is a chain hanging off the first SELECT.
 *
 * A SELECT with its own ORDER BY or LIMIT is parenthesized before a union
 * list is appended, as the server would otherwise read those clauses as the
 * union's. Those releases have no common table expressions.
 *
 * @visibility SqlSemantics
 */
trait LegacyUnions
{
    /**
     * Appends `UNION ALL` to the right-recursive union chain of a 5.x SELECT.
     *
     * @throws CompositionException When an operand is not a SELECT that can take a union list
     */
    protected function legacyUnion(Element $left, Element $right): Element
    {
        $left = $this->legacyOperand($this->expect($left, 'select_init', 'A set operand'));
        $right = $this->expect($right, 'select_init', 'A set operand');
        $init2 = $this->classOf('select_init2', ['select_part2', 'union_clause']);
        $appended = false;
        $append = function (Element $clause) use ($right, &$appended): Element {
            $appended = true;

            return $this->appendUnion($clause, $right);
        };
        $joined = $left->map(function (Element $child) use ($append, $init2): Element {
            if ($this->isUnionClause($child)) {
                return $append($child);
            }
            if ($init2 !== null && $child instanceof $init2) {
                return $child->map(fn (Element $grandchild): Element => $this->isUnionClause($grandchild) ? $append($grandchild) : $grandchild);
            }

            return $child;
        });
        if (!$appended) {
            throw new CompositionException('A set operand of ' . $this->language->version . ' must be a SELECT that can take a union list.');
        }

        return $joined;
    }

    /**
     * Parenthesizes a 5.x SELECT whose own ORDER BY or LIMIT would otherwise be read as the union's.
     */
    protected function legacyOperand(Element $select): Element
    {
        $plain = $this->classOf('select_init', ['SELECT_SYM', 'select_part2', 'opt_union_clause']) ?? $this->classOf('select_init', ['SELECT_SYM', 'select_init2']);
        if ($plain === null || !$select instanceof $plain) {
            return $select;
        }
        $part = $select->children()[0];
        $init2 = $this->classOf('select_init2', ['select_part2', 'union_clause']);
        if ($init2 !== null && $part instanceof $init2) {
            $part = $part->children()[0];
        }
        $ordered = false;
        foreach (Traversal::walk($part) as $value) {
            if (($value instanceof ($this->roleInterface('order_clause')) || $value instanceof ($this->roleInterface('limit_clause'))) && Writer::render($value) !== '' && $this->isClauseOf($part, $value)) {
                $ordered = true;
                break;
            }
        }
        if (!$ordered) {
            return $select;
        }

        return $this->form('select_init', ['(', 'select_paren', ')', 'union_opt'], [$this->form('select_paren', ['SELECT_SYM', 'select_part2'], [$part]), $this->form('union_opt', [], [])]);
    }

    /**
     * Reports whether a clause belongs to a SELECT body itself rather than to a subquery inside it.
     */
    protected function isClauseOf(Element $part, Element $clause): bool
    {
        foreach ($part->children() as $child) {
            if ($child === $clause) {
                return true;
            }
            if ($child instanceof ($this->roleInterface('select_into')) && in_array($clause, $child->children(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports whether a value occupies one of the union clause roles of the 5.x grammars.
     */
    protected function isUnionClause(Element $value): bool
    {
        foreach (['opt_union_clause', 'union_clause', 'union_opt'] as $rule) {
            if ($value instanceof ($this->roleInterface($rule))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Appends a SELECT to a union clause: an empty clause becomes `UNION ALL`, and a union list grows at its end.
     *
     * @throws CompositionException When the clause already ends with ORDER BY or LIMIT
     */
    protected function appendUnion(Element $clause, Element $right): Element
    {
        foreach (['opt_union_clause', 'union_clause', 'union_opt'] as $rule) {
            $empty = $this->classOf($rule, []);
            if ($empty !== null && $clause instanceof $empty) {
                return $this->form('union_list', ['UNION_SYM', 'union_option', 'select_init'], [$this->form('union_option', ['ALL'], []), $right]);
            }
        }
        $list = $this->classOf('union_list', ['UNION_SYM', 'union_option', 'select_init']);
        if ($list !== null && $clause instanceof $list) {
            return $clause->map(fn (Element $child): Element => $child instanceof SelectInitForm ? $this->legacyUnion($child, $right) : $child);
        }

        throw new CompositionException('A set operand of ' . $this->language->version . ' must be a SELECT without ORDER BY or LIMIT after its union list.');
    }
}
