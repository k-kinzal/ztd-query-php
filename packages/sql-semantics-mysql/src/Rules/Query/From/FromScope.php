<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\From;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Query\TableShapes;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\EscapedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\OdbcJoin;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Relation;

/**
 * Derives the terms of a FROM clause and the relations they make visible to names.
 *
 * Rule: MYSQL-FROM-SCOPE-001. A table reference, a derived table and a JSON
 * table are each one visible relation under their correlation name, or a
 * table reference under its table name when it has none, together with the
 * INVISIBLE columns a name finds (MYSQL-TABLE-SHAPES-001); DUAL makes none
 * visible. A non-lateral derived table sees the enclosing queries only; a
 * LATERAL derived table and the document of JSON_TABLE also see the tables
 * to their left: the earlier members of the comma list and, inside a join,
 * the left operand. A join combines its operands by MYSQL-JOIN-COLUMNS-001
 * and derives its ON condition where exactly its two operands are visible,
 * before NULL extension. Parentheses, braces and comma lists group terms and
 * change no name. Terminates: comma lists are walked in a loop; recursion
 * follows the strictly smaller operands. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/join.html,
 * https://dev.mysql.com/doc/refman/8.4/en/lateral-derived-tables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/json-table-functions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class FromScope
{
    /**
     * Derives one term, records its facts and answers what it makes visible.
     *
     * @param list<VisibleRelation> $left The relations to the left of the term in its FROM clause
     */
    public function open(Relation $term, Derivation $derivation, Environment $outer, array $left): JoinedInput
    {
        if ($term instanceof TableList || $term instanceof JoinedTable || $term instanceof NestedRelation || $term instanceof OdbcJoin || $term instanceof EscapedRelation) {
            $input = $this->enter($term, $derivation, $outer, $left);
            $derivation->target($term, $input->fact);

            return $input;
        }
        $lateral = $term instanceof JsonTable || ($term instanceof DerivedTable && $term->lateral);
        $fact = $derivation->relation($term, $lateral ? new Environment($derivation->context, $outer, $left) : $outer);
        if ($term instanceof NamedRelation) {
            $relation = new VisibleRelation($term, $fact->shape, $term->alias(), $term->name(), [], (new TableShapes())->implicit($fact));
        } elseif ($term instanceof DerivedTable || $term instanceof JsonTable) {
            $relation = new VisibleRelation($term, $fact->shape, $term->alias);
        } else {
            return new JoinedInput($fact, [], []);
        }
        $star = [];
        foreach (array_keys($fact->shape->slots) as $position) {
            $star[] = [0, $position];
        }

        return new JoinedInput($fact, [$relation], $star);
    }

    /**
     * Derives the terms of a grouping term without recording the facts of the term itself.
     *
     * @param list<VisibleRelation> $left The relations to the left of the term in its FROM clause
     */
    public function enter(TableList|JoinedTable|NestedRelation|OdbcJoin|EscapedRelation $term, Derivation $derivation, Environment $outer, array $left): JoinedInput
    {
        if ($term instanceof TableList) {
            $visible = [];
            $star = [];
            foreach ($term->members as $member) {
                $input = $this->open($member, $derivation, $outer, [...$left, ...$visible]);
                $offset = count($visible);
                array_push($visible, ...$input->visible);
                foreach ($input->star as [$relation, $position]) {
                    $star[] = [$relation + $offset, $position];
                }
            }

            return JoinedInput::of($visible, $star);
        }
        if ($term instanceof JoinedTable) {
            $first = $this->open($term->left, $derivation, $outer, $left);
            $second = $this->open($term->right, $derivation, $outer, [...$left, ...$first->visible]);
            if ($term->on !== null) {
                (new Operands())->single($derivation->scalar($term->on, new Environment($derivation->context, $outer, [...$first->visible, ...$second->visible])), $derivation);
            }

            return (new Joining())->join($derivation, $first, $second, $term);
        }

        return $this->open($term->relation, $derivation, $outer, $left);
    }
}
