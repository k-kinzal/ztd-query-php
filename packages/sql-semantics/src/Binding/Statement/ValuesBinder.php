<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Expression;

/**
 * Retains VALUES row boundaries and binds each value expression.
 *
 * @visibility SqlSemantics
 */
final class ValuesBinder
{
    /**
     * @return list<list<Expression>>
     */
    public function rows(Node $statement, Scope $scope): array
    {
        $nodes = Tree::outer($statement, ['values_clause', 'merge_values_clause', 'mvalues', 'values', 'values_list', 'table_value_constructor']);
        $rows = [];
        foreach ($nodes as $node) {
            foreach (Tree::outer($node, ['expr_list', 'exprlist', 'nexprlist', 'row_value', 'row_value_explicit', 'no_braces']) as $row) {
                $expressions = Tree::outer($row, ['a_expr', 'expr_or_default', 'expr']);
                $rows[] = array_map(static fn (Node $expression): Expression => (new ExpressionBinder())->bind($expression, $scope), $expressions);
            }
        }
        return $rows;
    }
    /**
     * @param non-empty-list<list<Expression>> $rows
     * @return list<\SqlSemantics\Model\OutputColumn>
     * @throws \SqlSemantics\SemanticException
     */
    public function outputs(array $rows, Node $source, Scope $scope): array
    {
        $outputs = [];
        foreach ($rows[0] as $ordinal => $first) {
            $values = [];
            foreach ($rows as $row) {
                if (count($row) !== count($rows[0])) {
                    $scope->diagnostics()->report('values-column-count', 'VALUES rows must have the same width.', $source);
                }
                if (isset($row[$ordinal])) {
                    $values[] = $row[$ordinal];
                }
            }
            $type = (new \SqlSemantics\Binding\TypeResolution($scope->identifiers->dialect, $scope->diagnostics()))->common($values, $source);
            $nullability = \SqlSemantics\Binding\NullFacts::alternatives($values);
            $expression = count($rows) === 1 ? $first : new Expression(\SqlSemantics\Model\ExpressionKind::Row, $type, $nullability, $source, $values, symbol: 'VALUES');
            $outputs[] = new \SqlSemantics\Model\OutputColumn($ordinal, 'column' . ($ordinal + 1), $expression);
        }
        return $outputs;
    }

}
