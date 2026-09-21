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
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

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
        foreach (Tree::outer($statement, ['set_clause', 'update_elem', 'ident_eq_value', 'setlist', 'SelectStmt', 'select', 'query_expression', 'opt_on_conflict', 'opt_insert_update_list', 'upsert', 'insert_update_list']) as $node) {
            if ($node->name === 'setlist') {
                array_push($nodes, ...array_reverse($node->find('setlist')));
            } elseif (in_array($node->name, ['set_clause', 'update_elem', 'ident_eq_value'], true)) {
                $nodes[] = $node;
            }
        }
        return array_map(fn (Node $node): Assignment => $this->assignment($node, $scope, $destinations), $nodes);
    }

    /**
     * Resolves every destination before checking value width and storage compatibility.
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
        $value = (new ExpressionBinder())->bind($valueNode, $scope);
        $row = Tree::outer($valueNode, ['implicit_row', 'explicit_row', 'exprlist', 'nexprlist'])[0] ?? null;
        if (count($targets) > 1 && $row !== null) {
            $values = array_map(static fn (Node $item): Expression => (new ExpressionBinder())->bind($item, $scope), Tree::outer($row, ['a_expr', 'expr']));
            $value = new Expression(ExpressionKind::Row, new TypeDescriptor($scope->identifiers->dialect, 'record'), Nullability::NotNull, $valueNode, $values, symbol: 'ROW');
        }
        $values = $value->kind === ExpressionKind::Row ? $value->operands : ($value->query === null ? [$value] : array_map(static fn ($output): Expression => $output->expression, $value->query->outputs));
        if (count($values) !== count($targets) && $value->query === null) {
            $scope->diagnostics()->report('assignment-column-count', 'Assignment destinations and values have different widths.', $node);
        }
        foreach ($targets as $index => $target) {
            if (isset($values[$index])) {
                (new AssignmentRules())->check($target, $values[$index], $scope);
            }
        }
        return new Assignment($targets, $value, $node);
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
}
