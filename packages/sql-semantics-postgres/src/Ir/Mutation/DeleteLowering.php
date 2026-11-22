<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation as ParsedComparison;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\CurrentOf;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Delete as ParsedDelete;

/**
 * Publishes a checked single-target DELETE from resolved analysis facts.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class DeleteLowering
{
    /**
     * Rejects unsupported clauses and closes the filter before exporting the target's slots.
     */
    public function lower(BoundStatement $operation, ParsedDelete $statement): Delete
    {
        $profile = $operation->context->profile;
        Check::input($profile->parameters === ParameterStyle::Native, 'The DELETE requires the native language profile.');
        Check::input($statement->with === null && $statement->using === null && $statement->returning === []
            && !$statement->where instanceof CurrentOf, 'Complete PostgreSQL DELETE requires one target without WITH, USING, a cursor or RETURNING.');
        $path = $operation->context->searchPath;
        $catalogFirst = ($path[0]->value === 'pg_temp' ? ($path[1]->value ?? null) : $path[0]->value) === 'pg_catalog';
        $binding = new SourceBinding($operation, $statement->target);
        $where = $this->predicate($statement->where, $binding, $catalogFirst);
        $target = $binding->source();
        Check::input($target !== null, 'A complete DELETE requires a resolved target.');

        return new Delete($target, $where, $profile->grammar);
    }

    /**
     * Resolves native signed integer operands or a constant truth value.
     */
    public function predicate(?Scalar $condition, SourceBinding $binding, bool $catalogFirst): BooleanLiteral|IntegerComparison|NullLiteral|null
    {
        while ($condition instanceof Grouped) {
            $condition = $condition->operand;
        }
        if ($condition === null) {
            return null;
        }
        $expression = new ExpressionLowering();
        if ($condition instanceof ParsedComparison) {
            $qualifiers = array_column($condition->operator->qualifiers, 'value');
            Check::input(($qualifiers === [] && $catalogFirst) || $qualifiers === ['pg_catalog'], 'A DELETE comparison must resolve to a pg_catalog operator.');
            $operator = IntegerOperator::tryFrom($condition->operator->name->value);
            Check::input($operator !== null, 'A DELETE comparison needs a supported integer operator.');
            $left = $expression->lower($condition->left, $binding, $catalogFirst);
            $right = $expression->lower($condition->right, $binding, $catalogFirst);
            Check::input(($left instanceof ColumnReference || $left instanceof IntegerLiteral)
                && ($right instanceof ColumnReference || $right instanceof IntegerLiteral), 'A DELETE comparison needs two signed integer operands.');

            return new IntegerComparison($operator, $left, $right);
        }
        $constant = $expression->lower($condition, $binding, $catalogFirst);
        Check::input($constant instanceof BooleanLiteral || $constant instanceof NullLiteral, 'A DELETE filter needs a Boolean or signed integer comparison.');

        return $constant;
    }
}
