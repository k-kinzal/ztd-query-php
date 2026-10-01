<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Semantic\Expression\Literal;
use SqlSemantics\Semantic\Expression\Parameter;
use SqlSemantics\Semantic\Projection\Fields;
use SqlSemantics\Semantic\Projection\Ordering;
use SqlSemantics\Semantic\Projection\OutputReference;
use SqlSemantics\Semantic\Scope;

/**
 * Resolves result-position ordering separately from input-column lookups.
 * @visibility SqlSemantics
 */
final class ModifiersReader
{
    /**
     * @return list<Ordering>
     */
    public function ordering(Node $statement, Fields $fields): array
    {
        $scope = $fields->scope;
        $syntax = $scope->dialect->platform()->syntax();
        $result = [];
        foreach ($scope->dialect->platform()->query()->orderingNodes($statement) as $node) {
            Tree::assertChildren($node, $syntax->nodes('orderingChildren'), [',']);
            $expr = Tree::child($node, $syntax->nodes('expression'));
            if ($expr === null) {
                Tree::unsupported($node, 'ordering');
            }
            $direction = Tree::child($node, $syntax->nodes('orderingDirection'));
            $nulls = Tree::child($node, $syntax->nodes('nullsOrder'));
            $key = $this->output($expr, $fields) ?? (new ExpressionReader())->read($expr, $scope);
            $result[] = new Ordering($key, $direction === null ? null : strtoupper(Tree::text($direction)) === 'DESC', $nulls === null ? null : str_contains(strtoupper(Tree::text($nulls)), 'FIRST'));
        }
        return $result;
    }

    /**
     * Resolves a sort key that names a result column or position.
     * @throws \SqlSemantics\Core\SemanticException
     */
    public function output(Node $node, Fields $fields): ?OutputReference
    {
        $tokens = $node->tokens();
        if (count($tokens) !== 1 || in_array($tokens[0]->name, $fields->scope->dialect->platform()->syntax()->nodes('stringToken'), true)) {
            return null;
        }
        $text = $tokens[0]->text;
        $outputs = $fields->outputs();
        if (ctype_digit($text)) {
            $position = (int) $text;
            if (!isset($outputs[$position - 1])) {
                throw new \SqlSemantics\Core\SemanticException('invalid-output-position', 'ORDER BY position is outside the result.', $node);
            }
            return new OutputReference($outputs[$position - 1], $position);
        }
        $name = (new Identifiers($fields->scope->dialect))->name($tokens[0]);
        $matches = [];
        foreach ($outputs as $index => $output) {
            if ($output->name !== null && $fields->scope->dialect->platform()->names()->equal($output->name->value, $name)) {
                $matches[] = new OutputReference($output, $index + 1, (new Names(new Identifiers($fields->scope->dialect)))->name($tokens[0]));
            }
        }
        if (count($matches) > 1) {
            throw new \SqlSemantics\Core\SemanticException('ambiguous-output', 'ORDER BY name is ambiguous.', $node);
        }
        return $matches[0] ?? null;
    }

    /**
     * @return array{Literal|Parameter|null, Literal|Parameter|null}
     */
    public function pagination(Node $statement, Scope $scope): array
    {
        $syntax = $scope->dialect->platform()->syntax();
        $limit = Tree::outer($statement, $syntax->nodes('limit'))[0] ?? null;
        $offset = Tree::outer($statement, $syntax->nodes('offset'))[0] ?? null;
        if ($limit !== null && $limit->tokens() !== [] && strtoupper($limit->tokens()[0]->text) !== 'LIMIT') {
            Tree::unsupported($limit, 'FETCH pagination');
        }
        $expressions = $limit === null ? [] : Tree::outer($limit, $syntax->nodes('paginationExpression'));
        $bound = array_map(fn (Node $node): Literal|Parameter => $this->count($node, $scope), $expressions);
        $skip = null;
        if ($offset !== null && $offset->tokens() !== []) {
            $nodes = Tree::outer($offset, $syntax->nodes('paginationExpression'));
            if (count($nodes) !== 1) {
                Tree::unsupported($offset, 'OFFSET');
            }
            $skip = $this->count($nodes[0], $scope);
        }
        if (count($bound) === 2 && $limit !== null) {
            return str_contains(Tree::text($limit), ',') ? [$bound[1], $bound[0]] : [$bound[0], $bound[1]];
        }
        return [$bound[0] ?? null, $skip];
    }

    /**
     * Reads a pagination value without inventing a parameter value.
     */
    public function count(Node $node, Scope $scope): Literal|Parameter
    {
        $value = (new ExpressionReader())->read($node, new Scope($scope->dialect));
        if (!$value instanceof Literal && !$value instanceof Parameter) {
            Tree::unsupported($node, 'pagination expression');
        }
        return $value;
    }
}
