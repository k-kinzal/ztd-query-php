<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Write;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\ExpressionRules;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Write\ConflictAction;

/**
 * Separates conflict inference and conditional updates from the main mutation.
 *
 * @visibility SqlSemantics
 */
final class ConflictBinder
{
    /**
     * @return list<ConflictAction>
     */
    public function bind(Node $statement, Scope $scope): array
    {
        $result = [];
        foreach (Tree::outer($statement, ['opt_on_conflict', 'opt_insert_update_list', 'insert_update_list', 'upsert', 'SelectStmt', 'select', 'query_expression']) as $node) {
            if (in_array($node->name, ['SelectStmt', 'select', 'query_expression'], true)) {
                continue;
            }
            $nodes = $node->name === 'upsert' ? $node->find('upsert') : [$node];
            foreach ($nodes as $clause) {
                if (Tree::hasTokens($clause)) {
                    $result[] = $this->action($clause, $scope);
                }
            }
        }
        return $result;
    }

    /**
     * Binds a single handler with its own inference and update predicates.
     */
    public function action(Node $node, Scope $scope): ConflictAction
    {
        $children = array_values(array_filter($node->children, static fn ($child): bool => !$child instanceof Node || $child->name !== 'upsert'));
        $source = new Node('conflict_action', 0, $children);
        $text = strtoupper(Tree::text($source));
        $inference = Tree::child($source, ['opt_conf_expr']);
        $keys = $inference === null ? [] : Tree::outer($inference, ['index_elem']);
        $sqlite = Tree::child($source, ['sortlist']);
        $keys = $sqlite === null ? $keys : Tree::outer($sqlite, ['expr']);
        $expressions = array_map(static fn (Node $key): Expression => (new ExpressionBinder())->bind(Tree::child($key, ['a_expr', 'expr', 'ColId']) ?? $key, $scope), $keys);
        $constraint = $inference === null ? null : Tree::child($inference, ['name']);
        $assignments = (new AssignmentBinder())->bind($source, $scope);
        $where = Tree::child($source, ['where_clause', 'where_opt']);
        $indexWhere = $inference === null ? Tree::child($source, ['where_opt']) : Tree::child($inference, ['where_clause']);
        $sqlitePredicates = array_values(array_filter($children, static fn ($child): bool => $child instanceof Node && $child->name === 'where_opt' && Tree::hasTokens($child)));
        if (count($sqlitePredicates) > 1) {
            $where = $sqlitePredicates[1];
        } elseif ($sqlite !== null) {
            $doSeen = false;
            $where = null;
            foreach ($children as $child) {
                $doSeen = $doSeen || (!$child instanceof Node && strtoupper($child->text) === 'DO');
                if ($doSeen && $child instanceof Node && $child->name === 'where_opt' && Tree::hasTokens($child)) {
                    $where = $child;
                }
            }
        }
        return new ConflictAction(str_contains($text, 'DO NOTHING') ? 'nothing' : 'update', $expressions, $constraint === null ? null : $scope->identifiers->parts($constraint)[0], $this->predicate($indexWhere, $scope), $assignments, $this->predicate($where, $scope), $source);
    }

    /**
     * Checks every row predicate under the dialect's boolean rules.
     */
    public function predicate(?Node $node, Scope $scope): ?Expression
    {
        $cursor = $node === null ? null : Tree::child($node, ['cursor_name']);
        if ($cursor !== null) {
            return new Expression(\SqlSemantics\Model\ExpressionKind::CurrentRow, new \SqlSemantics\Type\TypeDescriptor($scope->identifiers->dialect, 'boolean'), \SqlSemantics\Type\Nullability::NotNull, $node, symbol: 'CURRENT OF', reference: $scope->identifiers->parts($cursor));
        }
        $expression = $node === null ? null : (Tree::outer($node, ['a_expr', 'expr'])[0] ?? null);
        if ($expression === null) {
            return null;
        }
        $bound = (new ExpressionBinder())->bind($expression, $scope);
        (new ExpressionRules($scope->identifiers->dialect, $scope->diagnostics()))->predicate($bound);
        return $bound;
    }
}
