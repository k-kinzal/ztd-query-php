<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows\Recursive;

use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalDefinition;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Query\With\CommonTableExpression;

/**
 * Resolves the recursive transition and its continuation over the working row.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
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
        $target = $query->targets[0] ?? null;
        Check::input(count($query->targets) === 1 && $target instanceof ExpressionTarget, 'A recursive step requires one additive result.');
        $change = $target->expression;
        $test = $query->where;
        while ($change instanceof Grouped) {
            $change = $change->operand;
        }
        while ($test instanceof Grouped) {
            $test = $test->operand;
        }
        Check::input($change instanceof BinaryOperation && $test instanceof BinaryOperation, 'A recursive step requires addition and a bound comparison.');
        $path = $operation->context->searchPath;
        $catalogFirst = ($path[0]->value === 'pg_temp' ? ($path[1]->value ?? null) : $path[0]->value) === 'pg_catalog';
        Check::input($this->operator($change, $catalogFirst) === '+', 'A recursive step requires addition.');
        $operator = IntegerOperator::tryFrom($this->operator($test, $catalogFirst));
        Check::input($operator !== null, 'A recursive step needs a signed comparison.');
        $binding->read($operation, $change->left, $source);
        $binding->read($operation, $test->left, $source);
        $constant = new SourceBinding($operation, null);
        $decoder = new ExpressionLowering();
        $increment = $decoder->lower($change->right, $constant, $catalogFirst);
        $boundary = $decoder->lower($test->right, $constant, $catalogFirst);
        Check::input($increment instanceof IntegerLiteral && $boundary instanceof IntegerLiteral, 'A recursive step needs signed literal operands.');

        return [$increment, $boundary, $operator];
    }

    /**
     * Confirms that this operator resolves to the native built-in.
     */
    public function operator(BinaryOperation $expression, bool $catalogFirst): string
    {
        $qualifiers = array_column($expression->operator->qualifiers, 'value');
        Check::input(
            ($qualifiers === [] && $catalogFirst) || $qualifiers === ['pg_catalog'],
            'A recursive operator must resolve to pg_catalog.'
        );

        return $expression->operator->name->value;
    }
}
