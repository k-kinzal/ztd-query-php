<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Resolution;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\FunctionTable;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Join;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinKind;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinOn;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonTable;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\ParenthesizedJoin;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\RelationList;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlTable;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation;

/**
 * Derives the FROM items of a query and the relations they make visible to names.
 *
 * Rule: PG-FROM-SCOPE-001. A table, a subquery, a function call, XMLTABLE
 * and JSON_TABLE are each one visible relation under their alias, or without
 * one under their table name, function name, `xmltable` or `json_table`; a
 * subquery without alias can only be read through `*` and unqualified names.
 * A function call, XMLTABLE, JSON_TABLE and a LATERAL subquery see the FROM
 * items before them (at the top level and on the left of the joins they are
 * on the right of; the left side of a RIGHT or FULL join is visible but must
 * not be referenced, PG-LATERAL-JOIN-001); any other item sees only the enclosing query. A join
 * follows PG-JOIN-001; its ON condition sees its two sides and the enclosing
 * query only. A join in parentheses with an alias hides the items inside and
 * is one relation under the alias; without an alias the parentheses change
 * nothing. The items of a comma list follow each other. Terminates: lists
 * are walked in loops; recursion follows the nesting of joins.
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-FROM,
 * https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-LATERAL. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class FromScope
{
    /**
     * Derives one FROM item, records its facts and answers what it makes visible.
     *
     * @param list<VisibleRelation> $left The relations of the FROM items before the item, which lateral items see
     */
    public function open(Relation $item, Derivation $derivation, Environment $outer, array $left): JoinedInput
    {
        if ($item instanceof Join || $item instanceof ParenthesizedJoin || $item instanceof RelationList) {
            $input = $this->enter($item, $derivation, $outer, $left);
            $derivation->target($item, $input->fact);

            return $input;
        }
        $lateral = $item instanceof FunctionTable || $item instanceof XmlTable || $item instanceof JsonTable || ($item instanceof DerivedTable && $item->lateral);
        $fact = $derivation->relation($item, $lateral && $left !== [] ? new Environment($derivation->context, $outer, $left) : $outer);

        return new JoinedInput($fact, [$this->visible($item, $fact)]);
    }

    /**
     * Answers the relation one FROM item that is not a join makes visible.
     */
    public function visible(Relation $item, RelationFact $fact): VisibleRelation
    {
        if ($item instanceof TableInput) {
            return new VisibleRelation($item, $fact->shape, $item->alias, $item->alias === null ? $item->table->name : null, [], (new TableShapes())->implicit($fact));
        }
        $alias = $item instanceof FunctionTable || $item instanceof XmlTable || $item instanceof JsonTable || $item instanceof DerivedTable ? $item->alias : null;
        $name = match (true) {
            $alias !== null => null,
            $item instanceof FunctionTable => $item->name(),
            $item instanceof XmlTable => new Name('xmltable'),
            $item instanceof JsonTable => new Name('json_table'),
            default => null,
        };

        return new VisibleRelation($item, $fact->shape, $alias, $name === null ? null : new QualifiedName($name));
    }

    /**
     * Derives the items of a join, of parentheses or of a comma list, without recording the facts of the node itself.
     *
     * @param list<VisibleRelation> $left The relations of the FROM items before the node, which lateral items see
     */
    public function enter(Join|ParenthesizedJoin|RelationList $node, Derivation $derivation, Environment $outer, array $left): JoinedInput
    {
        if ($node instanceof RelationList) {
            $visible = [];
            foreach ($node->items as $item) {
                array_push($visible, ...$this->open($item, $derivation, $outer, [...$left, ...$visible])->visible);
            }

            return new JoinedInput(new RelationFact((new Visibility())->star($visible)), $visible);
        }
        if ($node instanceof ParenthesizedJoin) {
            $inner = $this->open($node->join, $derivation, $outer, $left);
            if ($node->alias === null) {
                return $inner;
            }
            $shape = (new ColumnAliases())->apply($inner->fact->shape, $node->alias, $node->columns, $derivation);

            return new JoinedInput(new RelationFact($shape), [new VisibleRelation($node, $shape, $node->alias)]);
        }
        $first = $this->open($node->left, $derivation, $outer, $left);
        $reach = $node->kind === JoinKind::Right || $node->kind === JoinKind::Full ? (new LateralReach())->bar($first->visible) : $first->visible;
        $second = $this->open($node->right, $derivation, $outer, [...$left, ...$reach]);
        if ($node->condition instanceof JoinOn) {
            $derivation->scalar($node->condition->condition, new Environment($derivation->context, $outer, [...$first->visible, ...$second->visible]));
        }

        return (new Joining())->join($node, $first, $second, $derivation);
    }
}
