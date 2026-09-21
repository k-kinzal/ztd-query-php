<?php

declare(strict_types=1);

namespace SqlSemantics\Binding;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Ordering;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\SemanticException;

/**
 * Reads ordering and pagination without confusing output aliases with input names.
 *
 * @visibility SqlSemantics
 */
final class SelectModifiersBinder
{
    /**
     * @param list<OutputColumn> $outputs
     * @return list<Ordering>
     */
    public function ordering(Node $statement, Scope $scope, array $outputs): array
    {
        $nodes = Tree::outer($statement, ['sortby', 'order_expr']);
        $sqlite = Tree::outer($statement, ['orderby_opt'])[0] ?? null;
        if ($sqlite !== null) {
            $nodes = array_reverse($sqlite->find('sortlist'));
        }
        $result = [];
        foreach ($nodes as $node) {
            Tree::assertChildren($node, ['sortlist', 'a_expr', 'expr', 'opt_asc_desc', 'opt_ordering_direction', 'ordering_direction', 'sortorder', 'opt_nulls_order', 'nulls'], [',']);
            $expr = Tree::child($node, ['a_expr', 'expr']);
            if ($expr === null) {
                Tree::unsupported($node, 'ordering');
            }
            $direction = Tree::child($node, ['opt_asc_desc', 'opt_ordering_direction', 'ordering_direction', 'sortorder']);
            $nulls = Tree::child($node, ['opt_nulls_order', 'nulls']);
            $expression = $this->sortExpression($expr, $scope, $outputs);
            $result[] = new Ordering($expression, $direction !== null && strtoupper(Tree::text($direction)) === 'DESC', $nulls === null ? null : str_contains(strtoupper(Tree::text($nulls)), 'FIRST'));
        }

        return $result;
    }

    /**
     * @param list<OutputColumn> $outputs
     * @throws SemanticException
     */
    public function sortExpression(Node $node, Scope $scope, array $outputs): Expression
    {
        $tokens = $node->tokens();
        if (count($tokens) === 1 && !in_array($tokens[0]->name, ['SCONST', 'USCONST', 'TEXT_STRING', 'STRING'], true)) {
            $text = $tokens[0]->text;
            if (ctype_digit($text)) {
                $ordinal = (int) $text - 1;
                if (!isset($outputs[$ordinal])) {
                    throw new SemanticException('invalid-output-position', 'ORDER BY position is outside the result.', $node);
                }
                return $outputs[$ordinal]->expression;
            }
            $name = $scope->identifiers->name($tokens[0]);
            $matches = array_values(array_filter($outputs, static fn (OutputColumn $output): bool => $output->name !== null && $scope->identifiers->equal($output->name, $name)));
            if (count($matches) > 1) {
                throw new SemanticException('ambiguous-output', 'ORDER BY alias is ambiguous.', $node);
            }
            if ($matches !== []) {
                return $matches[0]->expression;
            }
        }

        return (new ExpressionBinder())->bind($node, $scope);
    }

    /**
     * @return array{?Expression, ?Expression}
     */
    public function pagination(Node $statement, Scope $scope): array
    {
        $limit = Tree::outer($statement, ['limit_clause', 'limit_opt'])[0] ?? null;
        $offset = Tree::outer($statement, ['offset_clause'])[0] ?? null;
        if ($limit !== null && $limit->tokens() !== [] && strtoupper($limit->tokens()[0]->text) !== 'LIMIT') {
            Tree::unsupported($limit, 'FETCH pagination');
        }
        $expressions = $limit === null ? [] : Tree::outer($limit, ['a_expr', 'expr', 'limit_option']);
        $bound = array_map(static fn (Node $node): Expression => (new ExpressionBinder())->bind($node, $scope), $expressions);
        if (count($bound) > 2) {
            Tree::unsupported($statement, 'pagination');
        }
        if ($limit !== null && count($bound) === 2 && str_contains(Tree::text($limit), ',')) {
            return [$bound[1], $bound[0]];
        }
        if ($offset !== null) {
            $node = Tree::outer($offset, ['a_expr', 'expr'])[0] ?? null;
            if ($node === null) {
                Tree::unsupported($offset, 'offset');
            }
            return [$bound[0] ?? null, (new ExpressionBinder())->bind($node, $scope)];
        }
        if ($limit !== null && $limit->tokens() !== [] && $bound === []) {
            Tree::unsupported($limit, 'limit');
        }

        return [$bound[0] ?? null, $bound[1] ?? null];
    }
}
