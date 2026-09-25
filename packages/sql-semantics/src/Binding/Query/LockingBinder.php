<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Query\Locking;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\TableUse;

/**
 * Resolves locking clauses within their owning SELECT scope.
 * @visibility SqlSemantics
 */
final class LockingBinder
{
    /**
     * Binds the locking clauses of one query block, looking inside the parentheses of a MySQL derived table, whose
     * MySQL 5 grammar writes the query block as a table factor; MySQL rejects a table locked by more than one clause
     * and a derived table or JSON_TABLE named after OF.
     *
     * @return list<Locking\RowLock>
     * @throws \SqlSemantics\InvalidSql
     */
    public static function bind(Node $source, Scope $scope): array
    {
        $body = QueryNodes::body($source);
        $block = $body->name === 'table_factor' ? $body : ($source->name === 'table_subquery' ? (Tree::child($source, ['subquery']) ?? $source) : $source);
        $nodes = QueryNodes::local($block, ['for_locking_item', 'locking_clause', 'select_lock_type', 'opt_select_lock_type']);
        $locks = [];
        foreach ($nodes as $node) {
            if (!Tree::hasTokens($node)) {
                continue;
            }
            $strengthNode = Tree::child($node, ['for_locking_strength', 'lock_strength']);
            $strengthText = strtoupper(Tree::text($strengthNode ?? $node));
            $strength = Locking\LockStrength::from($strengthText === 'LOCK IN SHARE MODE' ? 'SHARE' : (str_starts_with($strengthText, 'FOR ') ? substr($strengthText, 4) : $strengthText));
            $waitNode = Tree::child($node, ['opt_nowait_or_skip', 'opt_locked_row_action']);
            $wait = Locking\LockWait::from($waitNode === null ? '' : strtoupper(Tree::text($waitNode)));
            $of = Tree::child($node, ['locked_rels_list', 'table_locking_list']);
            $targets = $of === null ? [] : Tree::outer($of, ['qualified_name', 'table_ident_opt_wild']);
            $locks[] = $targets === [] ? new Locking\AllRowLock($strength, $wait) : new Locking\NamedRowLock($strength, array_map(static fn (Node $target): TableUse|Locking\UnresolvedLockRelation => self::target($target, $scope), $targets), $wait);
        }
        if ($scope->identifiers->dialect === \SqlSemantics\Dialect::MySql && Locking\LockPlacement::repeated($locks, $scope->relations)) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::RepeatedLock, $source);
        }
        if ($scope->identifiers->dialect === \SqlSemantics\Dialect::MySql && Locking\LockPlacement::derived($locks)) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::DerivedLockTarget, $source);
        }
        return $locks;
    }

    /**
     * Resolves the locking clauses of a VALUES query block, which MySQL accepts and PostgreSQL rejects.
     *
     * @return list<Locking\RowLock>
     * @throws \SqlSemantics\InvalidSql
     */
    public static function values(Node $source, Scope $scope): array
    {
        $locks = self::bind($source, $scope);
        if ($locks !== [] && $scope->identifiers->dialect !== \SqlSemantics\Dialect::MySql) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::ValuesLock, $source);
        }
        return $locks;
    }

    /**
     * Finds the MySQL locking clause lists written after a set operation, between the query source and the set-operation body.
     *
     * MySQL applies such a clause to the last query block of the set operation, so the binder hands it to the rightmost operand.
     *
     * @return list<Node>
     */
    public static function trailing(Node $source, Node $body): array
    {
        $lists = [];
        $node = $body;
        while (($parent = QueryNodes::parentOf($source, $node)) !== null) {
            foreach ($parent->children as $child) {
                if ($child instanceof Node && $child->name === 'locking_clause_list' && Tree::hasTokens($child)) {
                    $lists[] = $child;
                }
            }
            $node = $parent;
        }
        return $lists;
    }

    /**
     * Finds a PostgreSQL row-locking clause attached to a set operation or to one of its operands.
     *
     * PostgreSQL rejects FOR UPDATE/SHARE on a UNION/INTERSECT/EXCEPT and on each of its leaf SELECTs,
     * including a parenthesized operand; subqueries inside an operand keep their own locking clauses.
     * The search follows the nodes from the query source down to the set-operation body, then the operands.
     */
    public static function setOperationLock(Node $source, Node $body): ?Node
    {
        if ($source === $body) {
            return self::operandLock($body);
        }
        $lock = self::attachedLock($source);
        if ($lock !== null) {
            return $lock;
        }
        foreach ($source->children as $child) {
            if ($child instanceof Node && ($child === $body || self::contains($child, $body))) {
                return self::setOperationLock($child, $body);
            }
        }
        return null;
    }

    /**
     * Finds a locking clause on a set-operation operand, looking through its parentheses and nested set operations.
     */
    public static function operandLock(Node $operand): ?Node
    {
        $lock = self::attachedLock($operand);
        if ($lock !== null) {
            return $lock;
        }
        foreach ($operand->children as $child) {
            if ($child instanceof Node && in_array($child->name, ['select_no_parens', 'select_with_parens', 'select_clause', 'simple_select'], true)) {
                $lock = self::operandLock($child);
                if ($lock !== null) {
                    return $lock;
                }
            }
        }
        return null;
    }

    /**
     * Returns a written locking clause that is a direct child of the node.
     */
    public static function attachedLock(Node $node): ?Node
    {
        foreach ($node->children as $child) {
            if ($child instanceof Node && in_array($child->name, ['for_locking_clause', 'opt_for_locking_clause'], true) && Tree::hasTokens($child)) {
                return $child;
            }
        }
        return null;
    }

    /**
     * Reports whether the node is the target or has it among its descendants.
     */
    public static function contains(Node $node, Node $target): bool
    {
        foreach ($node->children as $child) {
            if ($child === $target || ($child instanceof Node && self::contains($child, $target))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Resolves only the current query's FROM namespace; outer aliases are not lock targets.
     */
    public static function target(Node $source, Scope $scope): TableUse|Locking\UnresolvedLockRelation
    {
        $name = $scope->identifiers->parts($source);
        $relations = array_values(array_filter($scope->relations, static fn (TableUse $relation): bool => $scope->matches($relation, $name)));
        if (count($relations) === 1) {
            return $relations[0];
        }
        $scope->diagnostics()->report($relations === [] ? 'unknown-lock-relation' : 'ambiguous-lock-relation', 'Cannot resolve lock target: ' . implode('.', $name), $source);
        return new Locking\UnresolvedLockRelation(new QualifiedName($name));
    }
}
