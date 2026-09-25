<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Binding;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Model\OutputColumn;
use SqlSemantics\Core\SemanticException;

/**
 * Binds output expressions and expands stars in declaration order.
 *
 * @visibility SqlSemantics
 */
final class ProjectionBinder
{
    /**
     * @return list<OutputColumn>
     */
    public function bind(Node $select, Scope $scope): array
    {
        $items = $scope->identifiers->dialect->platform()->query()->projectionItems($select);
        if ($items === []) {
            $list = Tree::child($select, $scope->identifiers->dialect->platform()->syntax()->nodes('projectionList'));
            if ($list !== null && Tree::text($list) === '*') {
                return $this->star([], $scope, $list, 0);
            }
            Tree::unsupported($select, 'empty projection');
        }
        $outputs = [];
        foreach ($items as $item) {
            array_push($outputs, ...$this->item($item, $scope, count($outputs)));
        }

        return $outputs;
    }

    /**
     * @return list<OutputColumn>
     */
    public function item(Node $item, Scope $scope, int $ordinal): array
    {
        $expression = Tree::child($item, $scope->identifiers->dialect->platform()->syntax()->nodes('projectionExpression'));
        $aliasNode = Tree::child($item, $scope->identifiers->dialect->platform()->syntax()->nodes('projectionAlias'));
        $tokens = $scope->identifiers->dialect->platform()->query()->projectionTokens($item, $expression);
        if ($tokens !== [] && $tokens[count($tokens) - 1]->text === '*') {
            $parts = [];
            foreach (array_slice($tokens, 0, -1) as $token) {
                if ($token->text !== '.') {
                    $parts[] = $scope->identifiers->name($token);
                }
            }
            return $this->star($parts, $scope, $item, $ordinal);
        }
        if ($expression === null) {
            Tree::unsupported($item, 'projection');
        }
        $bound = (new ExpressionBinder())->bind($expression, $scope);
        $bound = $scope->identifiers->dialect->platform()->types()->project($bound);
        $alias = null;
        if ($aliasNode !== null) {
            $aliasTokens = $aliasNode->tokens();
            $alias = $scope->identifiers->name($aliasTokens[count($aliasTokens) - 1]);
        }

        return [new OutputColumn($ordinal, $alias ?? $bound->binding?->column->name, $bound)];
    }

    /**
     * @param list<string> $qualifiers
     * @return list<OutputColumn>
     * @throws SemanticException
     */
    public function star(array $qualifiers, Scope $scope, Node $source, int $ordinal): array
    {
        $outputs = [];
        foreach ($scope->relations as $relation) {
            if (!$scope->matches($relation, $qualifiers)) {
                continue;
            }
            foreach ($relation->declaration->columns as $column) {
                $bound = $scope->column([$relation->alias ?? $relation->declaration->name, $column->name], $source);
                $outputs[] = new OutputColumn($ordinal + count($outputs), $column->name, $bound);
            }
        }
        if ($outputs === []) {
            throw new SemanticException('unknown-relation', 'Star has no matching relation.', $source);
        }

        return $outputs;
    }
}
