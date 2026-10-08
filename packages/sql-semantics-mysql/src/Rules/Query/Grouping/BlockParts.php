<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Grouping;

use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\EscapedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\OdbcJoin;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;

/**
 * Takes apart the clauses of a query block whose equalities and keys determine columns.
 *
 * The grouping check (MYSQL-ONLY-FULL-GROUP-BY-001) reads the equalities of WHERE and of the
 * join conditions, each an AND operand of its condition, and the table occurrences of the FROM
 * clause through joins, lists, parentheses and ODBC escapes; a derived table or a common table
 * is an occurrence whose rows come from the single query block it is written as.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/group-by-handling.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class BlockParts
{
    /**
     * Answers the AND operands of a condition.
     *
     * @return list<Scalar>
     */
    public function conjuncts(?Scalar $condition): array
    {
        if ($condition === null) {
            return [];
        }
        $condition = (new Matching())->unwrap($condition);
        if ($condition instanceof Logical && $condition->operator === LogicalOperator::And) {
            return [...$this->conjuncts($condition->left), ...$this->conjuncts($condition->right)];
        }

        return [$condition];
    }

    /**
     * Answers the joins of a FROM clause or of a part of it, outside derived tables.
     *
     * @return list<JoinedTable>
     */
    public function joins(?Relation $relation): array
    {
        if ($relation instanceof JoinedTable) {
            return [$relation, ...$this->joins($relation->left), ...$this->joins($relation->right)];
        }
        $joins = [];
        foreach ($this->parts($relation) ?? [] as $part) {
            array_push($joins, ...$this->joins($part));
        }

        return $joins;
    }

    /**
     * Answers the table occurrences of a FROM clause or of a part of it, outside derived tables.
     *
     * @return list<Relation>
     */
    public function leaves(?Relation $relation): array
    {
        if ($relation instanceof JoinedTable) {
            return [...$this->leaves($relation->left), ...$this->leaves($relation->right)];
        }
        $parts = $this->parts($relation);
        if ($parts === null) {
            return $relation === null ? [] : [$relation];
        }
        $leaves = [];
        foreach ($parts as $part) {
            array_push($leaves, ...$this->leaves($part));
        }

        return $leaves;
    }

    /**
     * Answers the relations a list, parentheses or an escape groups, or null for a table occurrence.
     *
     * @return list<Relation>|null
     */
    public function parts(?Relation $relation): ?array
    {
        return match (true) {
            $relation instanceof TableList => $relation->members,
            $relation instanceof NestedRelation, $relation instanceof OdbcJoin, $relation instanceof EscapedRelation => [$relation->relation],
            default => null,
        };
    }

    /**
     * Answers the occurrences of a part of a FROM clause, among the given ones.
     *
     * @param array<int, VisibleRelation> $relations
     * @return array<int, VisibleRelation>
     */
    public function members(Relation $part, array $relations): array
    {
        $members = [];
        foreach ($this->leaves($part) as $leaf) {
            if (isset($relations[spl_object_id($leaf)])) {
                $members[spl_object_id($leaf)] = $relations[spl_object_id($leaf)];
            }
        }

        return $members;
    }

    /**
     * Answers the occurrences of the FROM clause of a block that the facts cover, by their object ids.
     *
     * @return array<int, VisibleRelation>
     */
    public function occurrences(Select $select, Facts $facts): array
    {
        $occurrences = [];
        foreach ($this->leaves($select->from) as $leaf) {
            if ($facts->covers($leaf)) {
                $occurrences[spl_object_id($leaf)] = new VisibleRelation($leaf, $facts->relation($leaf)->shape);
            }
        }

        return $occurrences;
    }

    /**
     * Answers the query block a derived table or a common table reads its rows from, when it is a single block.
     */
    public function block(Relation $relation, Facts $facts): ?Select
    {
        $query = null;
        if ($relation instanceof DerivedTable) {
            $query = $relation->query;
        } elseif ($relation instanceof TableReference && $facts->covers($relation)) {
            $table = $facts->relation($relation)->table;
            $query = $table instanceof CommonTable && $table->definition instanceof CommonTableExpression ? $table->definition->query : null;
        }
        while ($query instanceof ParenthesizedQuery || $query instanceof QueryExpression) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }

        return $query instanceof Select ? $query : null;
    }
}
