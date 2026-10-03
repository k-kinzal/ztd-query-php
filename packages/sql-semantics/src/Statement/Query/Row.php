<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Query;

use SqlSemantics\Statement\Expression\Reference\Ownership;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\SemanticGraph;

/**
 * An ordered row of expressions resolved in one input scope.
 * @visibility public
 * @example Describing a row without evaluating it
 *     $scope = new \SqlSemantics\Statement\Relation\Scope(new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main'))));
 *     (new \SqlSemantics\Statement\Query\Row($scope, new \SqlSemantics\Statement\Expression\NullConstant()))->toString() // => '(NULL)'
 */
final class Row
{
    /**
     * @var non-empty-list<ScalarExpression>
     */
    public readonly array $expressions;

    /**
     * Every operand is semantic and belongs to this row's expression scope.
     */
    public function __construct(public readonly Scope $scope, ScalarExpression $first, ScalarExpression ...$rest)
    {
        $this->expressions = [$first, ...array_values($rest)];
        foreach ($this->expressions as $expression) {
            assert((new SemanticGraph())->containsOnlyValues($expression), 'A row retains only semantic expressions.');
            assert((new Ownership())->accepts($expression, $scope), 'A row expression must retain its input scope.');
        }
    }

    /**
     * Reconstructs the tuple in its original expression order.
     */
    public function toString(): string
    {
        return '(' . implode(', ', array_map(static fn (ScalarExpression $expression): string => $expression->toString(), $this->expressions)) . ')';
    }
}
