<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Window;

/**

 * Binds window references and frame boundaries without flattening their positions. @visibility SqlSemantics

 */
final class WindowBinder
{
    /**
     * Reads the window reference or specification owned by this invocation.
     */
    public function bind(Node $node, Scope $scope): Window\Window
    {
        $spec = Tree::outer($node, ['window_specification', 'window_spec_details', 'window'])[0] ?? null;
        if ($spec === null) {
            $name = Tree::outer($node, ['ColId', 'ident', 'nm', 'window_name'])[0] ?? null;
            if ($name === null) {
                Tree::invalid($node, 'window reference');
            }
            return new Window\NamedWindow($scope->identifiers->parts($name)[0]);
        }
        $base = Tree::child($spec, ['opt_existing_window_name', 'opt_existing_window_name', 'window_name', 'nm']);
        $partition = Tree::child($spec, ['opt_partition_clause', 'nexprlist']);
        $parts = array_map(static fn (Node $expression) => (new ExpressionBinder())->bind($expression, $scope), $partition === null ? [] : Tree::outer($partition, ['a_expr', 'expr']));
        $frame = Tree::child($spec, ['opt_frame_clause', 'opt_window_frame_clause', 'frame_opt']);
        return new Window\WindowSpecification($base === null ? null : $scope->identifiers->parts($base)[0], $parts, (new \SqlSemantics\Binding\SelectModifiersBinder())->ordering($spec, $scope, null), $frame === null ? null : $this->frame($frame, $scope));
    }

    /**
     * Binds the frame unit, boundaries, and row exclusion policy.
     */
    public function frame(Node $node, Scope $scope): Window\Frame
    {
        $words = array_map(static fn ($token): string => strtoupper($token->text), $node->tokens());
        $bounds = Tree::outer($node, ['frame_bound', 'window_frame_bound', 'window_frame_start']);
        if ($bounds === []) {
            Tree::invalid($node, 'window frame boundaries');
        }
        $exclusion = Window\FrameExclusion::None;
        $position = array_search('EXCLUDE', $words, true);
        if ($position !== false) {
            $exclusion = Window\FrameExclusion::from(implode(' ', array_slice($words, $position + 1)));
        }
        return new Window\Frame(Window\FrameUnit::from($words[0]), $this->boundary($bounds[0], $scope), isset($bounds[1]) ? $this->boundary($bounds[1], $scope) : new Window\CurrentRow(), $exclusion);
    }

    /**
     * Classifies an unbounded, current-row, or expression-offset frame boundary.
     */
    public function boundary(Node $node, Scope $scope): Window\Boundary
    {
        $text = strtoupper(Tree::text($node));
        if ($text === 'CURRENT ROW') {
            return new Window\CurrentRow();
        }
        $direction = str_ends_with($text, 'PRECEDING') ? Window\Direction::Preceding : Window\Direction::Following;
        if (str_starts_with($text, 'UNBOUNDED ')) {
            return new Window\Unbounded($direction);
        }
        $value = Tree::outer($node, ['a_expr', 'expr', 'NUM_literal', 'param_marker'])[0] ?? null;
        if ($value === null) {
            Tree::invalid($node, 'window offset');
        }
        return new Window\Offset($direction, (new ExpressionBinder())->bind($value, $scope));
    }
}
