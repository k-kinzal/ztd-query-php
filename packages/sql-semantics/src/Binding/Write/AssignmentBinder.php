<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Write;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Write\Assignment;

/**
 * Binds ordered scalar and tuple assignments, keeping qualified destinations intact.
 *
 * @visibility SqlSemantics
 */
final class AssignmentBinder
{
    /**
     * @return list<Assignment>
     */
    public function bind(Node $statement, Scope $scope, ?Scope $destinations = null): array
    {
        $nodes = [];
        foreach (Tree::outer($statement, ['set_clause', 'update_elem', 'insert_update_elem', 'ident_eq_value', 'setlist', 'SelectStmt', 'select', 'query_expression', 'opt_on_conflict', 'opt_insert_update_list', 'upsert', 'insert_update_list', 'with_clause', 'opt_with_clause', 'with']) as $node) {
            if ($node->name === 'setlist') {
                array_push($nodes, ...array_reverse($node->find('setlist')));
            } elseif (in_array($node->name, ['set_clause', 'update_elem', 'insert_update_elem', 'ident_eq_value'], true)) {
                $nodes[] = $node;
            }
        }
        return array_map(fn (Node $node): Assignment => $this->assignment($node, $scope, $destinations), $nodes);
    }

    /**
     * Resolves every destination before checking value width and storage compatibility.
     * @throws \SqlSemantics\InvalidSql
     */
    public function assignment(Node $node, Scope $scope, ?Scope $destinations = null): Assignment
    {
        $list = Tree::child($node, ['set_target_list', 'idlist']);
        $names = $list === null ? array_filter([Tree::child($node, ['set_target', 'simple_ident_nospvar', 'nm'])]) : Tree::outer($list, ['set_target', 'nm']);
        $targets = array_map(fn (Node $name): Expression => $this->target($name, $destinations ?? $scope), $names);
        $valueNode = Tree::child($node, ['a_expr', 'expr', 'expr_or_default']);
        if ($targets === [] || $valueNode === null) {
            Tree::invalid($node, 'assignment');
        }
        $paths = array_map(StoragePathBinder::bind(...), $targets);
        $default = $list === null ? WriteInputs::value($valueNode, $scope) : null;
        if ($default instanceof \SqlSemantics\Model\Write\DefaultSource) {
            return new Assignment\DefaultAssignment($paths[0], $node);
        }
        $tuple = $list === null ? null : WriteInputs::tuple($valueNode, $scope);
        if ($tuple !== null) {
            if (count($tuple->items) !== count($paths)) {
                throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::AssignmentWidth, $node);
            }
            foreach ($paths as $index => $path) {
                (new AssignmentRules())->checkPath($path, $tuple->items[$index], $scope);
            }
            return new Assignment\TupleRowAssignment($paths, $tuple, $node);
        }
        $value = $default ?? (new ExpressionBinder())->bind($valueNode, $scope, true);
        $values = $list === null ? [$value] : ($value->kind === ExpressionKind::Row ? $value->inputs() : ($value->subquery() === null ? [$value] : array_map(static fn ($output): Expression => $output->expression, $value->subquery()->resultColumns())));
        if (count($values) !== count($targets) && ($value->subquery() === null || \SqlSemantics\Model\Validation\RowShape::width($value->subquery()) !== null)) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::AssignmentWidth, $node);
        }
        foreach ($targets as $index => $target) {
            if (isset($values[$index])) {
                (new AssignmentRules())->check($target, $values[$index], $scope);
            }
        }
        return $this->form($node, $list !== null, $value, $paths, $scope);
    }

    /**
     * Retains subscript and record-field destinations as structured access expressions.
     */
    public function target(Node $name, Scope $scope): Expression
    {
        $base = Tree::child($name, ['ColId']);
        if ($base !== null && Tree::child($name, ['opt_indirection']) !== null) {
            $value = $scope->column($scope->identifiers->parts($base), $base);
            foreach (Tree::outer($name, ['indirection_el']) as $element) {
                $value = (new \SqlSemantics\Binding\Scalar\IndirectionBinder())->apply($value, $element, $scope);
            }
            return $value;
        }
        return $scope->column($scope->identifiers->parts($base ?? $name), $name);
    }

    /**
     * @param list<\SqlSemantics\Model\Write\Storage\Path> $paths Ordered write destinations
     * @throws \SqlSemantics\InvalidSql
     */
    public function form(Node $node, bool $tuple, Expression $value, array $paths, Scope $scope): Assignment
    {
        if (count($paths) === 1 && (!$tuple || $scope->identifiers->dialect === \SqlSemantics\Dialect::Sqlite)) {
            return new Assignment\ScalarAssignment($paths[0], $value, $node);
        }
        if ($value instanceof \SqlSemantics\Model\Scalar\Value\RowExpression) {
            return new Assignment\TupleRowAssignment($paths, new \SqlSemantics\Model\Write\InputRow($scope->identifiers->dialect, $value->items), $node);
        }
        if ($value instanceof \SqlSemantics\Model\Scalar\Query\ScalarSubquery || $value instanceof \SqlSemantics\Model\Scalar\Query\RowSubquery) {
            return new Assignment\TupleQueryAssignment($paths, $value->query, $node);
        }
        throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::TupleSource, $node);
    }
}
