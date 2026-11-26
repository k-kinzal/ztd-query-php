<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows\Recursive;

use SqlSemantics\Platform\MySql\Ir\ExpressionLowering;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalDefinition;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;

/**
 * Resolves the recursive transition and its continuation over the working row.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RecursiveStepLowering
{
    /**
     * Extracts the signed change, boundary and strict comparison.
     *
     * @return array{IntegerLiteral, IntegerLiteral, IntegerOperator}
     */
    public function lower(BoundStatement $operation, Select $query, CommonTableExpression $definition): array
    {
        (new LocalDefinition())->queryBlock($query);
        $binding = new RecursiveBinding();
        $source = $binding->source($operation, $query, $definition);
        $target = $query->items[0];
        Check::input(count($query->items) === 1 && $target instanceof SelectExpression, 'A recursive step requires one additive result.');
        $change = $target->expression;
        $test = $query->where;
        while ($change instanceof Grouped) {
            $change = $change->operand;
        }
        while ($test instanceof Grouped) {
            $test = $test->operand;
        }
        Check::input($change instanceof Arithmetic && $test instanceof Comparison, 'A recursive step requires addition and a bound comparison.');
        Check::input($change->operator->value === '+', 'A recursive step requires addition.');
        $operator = IntegerOperator::from($test->operator->value);
        $binding->read($operation, $change->left, $source);
        $binding->read($operation, $test->left, $source);
        $constant = new SourceBinding($operation, null);
        $decoder = new ExpressionLowering();
        $increment = $decoder->lower($change->right, $constant);
        $boundary = $decoder->lower($test->right, $constant);
        Check::input($increment instanceof IntegerLiteral && $boundary instanceof IntegerLiteral, 'A recursive step needs signed literal operands.');

        return [$increment, $boundary, $operator];
    }
}
