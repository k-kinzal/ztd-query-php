<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Query;
use SqlSemantics\Model\Window;

/**

 * Binds duplicate elimination and named windows as query-owned semantic operations. @visibility SqlSemantics

 */
final class ProjectionOptions
{
    /**
     * Reads ALL, DISTINCT, or DISTINCT ON with its required key expressions.
     */
    public function quantifier(Node $source, Scope $scope): Query\Quantifier
    {
        $node = QueryNodes::local($source, ['distinct_clause', 'select_options', 'distinct'])[0] ?? null;
        if ($node === null || !str_contains(strtoupper(Tree::text($node)), 'DISTINCT')) {
            return new Query\AllRows();
        }
        $keys = Tree::outer($node, ['a_expr', 'expr']);
        return $keys === [] ? new Query\DistinctRows() : new Query\DistinctOn(array_map(static fn (Node $key) => (new \SqlSemantics\Binding\ExpressionBinder())->bind($key, $scope), $keys));
    }

    /**

     * @return list<Window\Definition>

     */
    public function windows(Node $source, Scope $scope): array
    {
        $definitions = [];
        foreach (QueryNodes::local($source, ['window_definition', 'window_def', 'windowdefn']) as $node) {
            $name = Tree::child($node, ['ColId', 'window_name', 'nm']);
            if ($name === null) {
                Tree::invalid($node, 'window name');
            }
            $spec = (new \SqlSemantics\Binding\Scalar\WindowBinder())->bind($node, $scope);
            if (!$spec instanceof Window\WindowSpecification) {
                Tree::invalid($node, 'window specification');
            }
            $definitions[] = new Window\Definition($scope->identifiers->parts($name)[0], $spec);
        }
        return $definitions;
    }
}
