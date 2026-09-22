<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement;

use SqlParser\Parser\Node;
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
        $rows = array_map(static fn (array $row): array => array_map(static fn (Node $expression): Expression => (new ExpressionBinder())->bind($expression, $scope), $row), ValueRows::nodes($statement));
        if ($rows !== []) {
            ValueRows::check($rows, $statement);
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
        ValueRows::check($rows, $source);
        $outputs = [];
        foreach ($rows[0] as $ordinal => $first) {
            $values = [];
            foreach ($rows as $row) {
                if (isset($row[$ordinal])) {
                    $values[] = $row[$ordinal];
                }
            }
            $type = (new \SqlSemantics\Binding\TypeResolution($scope->identifiers->dialect, $scope->diagnostics()))->common($values, $source);
            $nullability = \SqlSemantics\Binding\NullFacts::alternatives($values);
            $expression = count($rows) === 1 ? $first : new \SqlSemantics\Model\Scalar\Value\ValuesColumn(new \SqlSemantics\Model\Scalar\ExpressionFacts($type, $nullability, []), $source, $values);
            $outputs[] = new \SqlSemantics\Model\OutputColumn($ordinal, 'column' . ($ordinal + 1), $expression);
        }
        return $outputs;
    }

}
