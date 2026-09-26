<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Binding;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Model\Expression;
use SqlSemantics\Core\Model\Ordering;
use SqlSemantics\Core\Model\OutputColumn;
use SqlSemantics\Core\SemanticException;

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
        $nodes = $scope->identifiers->dialect->platform()->query()->orderingNodes($statement);
        $result = [];
        foreach ($nodes as $node) {
            Tree::assertChildren($node, $scope->identifiers->dialect->platform()->syntax()->nodes('orderingChildren'), [',']);
            $expr = Tree::child($node, $scope->identifiers->dialect->platform()->syntax()->nodes('expression'));
            if ($expr === null) {
                Tree::unsupported($node, 'ordering');
            }
            $direction = Tree::child($node, $scope->identifiers->dialect->platform()->syntax()->nodes('orderingDirection'));
            $nulls = Tree::child($node, $scope->identifiers->dialect->platform()->syntax()->nodes('nullsOrder'));
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
        if (count($tokens) === 1 && !in_array($tokens[0]->name, $scope->identifiers->dialect->platform()->syntax()->nodes('stringToken'), true)) {
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
        $limit = Tree::outer($statement, $scope->identifiers->dialect->platform()->syntax()->nodes('limit'))[0] ?? null;
        $offset = Tree::outer($statement, $scope->identifiers->dialect->platform()->syntax()->nodes('offset'))[0] ?? null;
        if ($limit !== null && $limit->tokens() !== [] && strtoupper($limit->tokens()[0]->text) !== 'LIMIT') {
            Tree::unsupported($limit, 'FETCH pagination');
        }
        $expressions = $limit === null ? [] : Tree::outer($limit, $scope->identifiers->dialect->platform()->syntax()->nodes('paginationExpression'));
        $bound = array_map(static fn (Node $node): Expression => (new ExpressionBinder())->bind($node, $scope), $expressions);
        if (count($bound) > 2) {
            Tree::unsupported($statement, 'pagination');
        }
        if ($limit !== null && count($bound) === 2 && str_contains(Tree::text($limit), ',')) {
            return [$bound[1], $bound[0]];
        }
        if ($offset !== null) {
            $node = Tree::outer($offset, $scope->identifiers->dialect->platform()->syntax()->nodes('expression'))[0] ?? null;
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
