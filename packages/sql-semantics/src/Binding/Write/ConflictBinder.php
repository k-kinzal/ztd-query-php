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
                if (Tree::hasTokens($clause) && !str_starts_with(strtoupper(Tree::text($clause)), 'RETURNING')) {
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
        $children = $node->name === 'insert_update_list' ? $node->find('insert_update_elem') : array_values(array_filter($node->children, static fn ($child): bool => !$child instanceof Node || $child->name !== 'upsert'));
        $source = new Node('conflict_action', 0, $children);
        $words = array_map(static fn ($child): string => strtoupper($child->text), array_values(array_filter($children, static fn ($child): bool => !$child instanceof Node)));
        $inference = Tree::child($source, ['opt_conf_expr']);
        $keys = $inference === null ? [] : Tree::outer($inference, ['index_elem']);
        $sqlite = Tree::child($source, ['sortlist']);
        $keys = $sqlite === null ? $keys : Tree::outer($sqlite, ['expr']);
        $expressions = array_map(static fn (Node $key): Expression => (new ExpressionBinder())->bind(Tree::child($key, ['a_expr', 'expr', 'ColId']) ?? $key, $scope), $keys);
        $constraint = $inference === null ? null : Tree::child($inference, ['name']);
        $assignments = (new AssignmentBinder())->bind($source, $scope);
        $where = Tree::child($source, ['where_clause', 'where_opt']);
        $indexWhere = $inference === null ? null : Tree::child($inference, ['where_clause']);
        if ($sqlite !== null) {
            [$indexWhere, $where] = self::upsertPredicates($children);
        }
        $target = $constraint !== null
            ? new \SqlSemantics\Model\Write\Conflict\ConstraintConflict($scope->identifiers->parts($constraint)[0])
            : ($expressions !== [] ? new \SqlSemantics\Model\Write\Conflict\IndexConflict($expressions, $this->predicate($indexWhere, $scope)) : new \SqlSemantics\Model\Write\Conflict\AnyConflict());
        return in_array('NOTHING', $words, true)
            ? new \SqlSemantics\Model\Write\Conflict\DoNothing($target, $source)
            : new \SqlSemantics\Model\Write\Conflict\DoUpdate($target, $assignments, $this->predicate($where, $scope), $source);
    }

    /**
     * Splits the SQLite upsert predicates at DO: the conflict-target WHERE precedes it and the DO UPDATE WHERE follows it.
     * @param list<Node|\SqlParser\Lexer\Token> $children
     * @return array{Node|null, Node|null}
     */
    public static function upsertPredicates(array $children): array
    {
        $predicates = [null, null];
        $after = 0;
        foreach ($children as $child) {
            if (!$child instanceof Node) {
                $after = strtoupper($child->text) === 'DO' ? 1 : $after;
            } elseif ($child->name === 'where_opt' && Tree::hasTokens($child)) {
                $predicates[$after] = $child;
            }
        }
        return $predicates;
    }

    /**
     * Checks every row predicate under the dialect's boolean rules.
     */
    public function predicate(?Node $node, Scope $scope): ?Expression
    {
        $cursor = $node === null ? null : Tree::child($node, ['cursor_name']);
        if ($cursor !== null) {
            return new \SqlSemantics\Model\Scalar\Reference\CursorPosition(new \SqlSemantics\Model\Scalar\ExpressionFacts(\SqlSemantics\Type\TypeDescriptor::builtin($scope->identifiers->dialect, 'boolean'), \SqlSemantics\Type\Nullability::NotNull, []), $node, $scope->identifiers->parts($cursor));
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
