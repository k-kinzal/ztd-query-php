<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Mutation;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\ExpressionLowering;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete as ParsedDelete;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison as ParsedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;

/**
 * Publishes a checked single-target DELETE from resolved analysis facts.
 *
 * @visibility SqlSemantics\Platform\MySql
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
        Check::input($profile->lexical->equals(new LexicalSettings()), 'This DELETE profile requires the default lexical settings.');
        Check::input(
            $statement->with === null && $statement->options === [] && $statement->orderBy === [] && $statement->limit === null,
            'Complete MySQL DELETE requires a single unmodified target without ordering or bounds.'
        );
        $binding = new SourceBinding($operation, $statement->table);
        $where = $this->predicate($statement->where, $binding);
        $target = $binding->source();
        Check::input($target !== null, 'A complete DELETE requires a resolved target.');

        return new Delete($target, $where, $profile->grammar);
    }

    /**
     * Resolves native signed integer operands or a constant truth value.
     */
    public function predicate(?Scalar $condition, SourceBinding $binding): BooleanLiteral|IntegerComparison|NullLiteral|null
    {
        while ($condition instanceof Grouped) {
            $condition = $condition->operand;
        }
        if ($condition === null) {
            return null;
        }
        $expression = new ExpressionLowering();
        if ($condition instanceof ParsedComparison) {
            $operator = IntegerOperator::tryFrom($condition->operator->value);
            Check::input($operator !== null, 'A DELETE comparison needs a supported integer operator.');
            $left = $expression->lower($condition->left, $binding);
            $right = $expression->lower($condition->right, $binding);
            Check::input(($left instanceof ColumnReference || $left instanceof IntegerLiteral)
                && ($right instanceof ColumnReference || $right instanceof IntegerLiteral), 'A DELETE comparison needs two signed integer operands.');

            return new IntegerComparison($operator, $left, $right);
        }
        $constant = $expression->lower($condition, $binding);
        Check::input($constant instanceof BooleanLiteral || $constant instanceof NullLiteral, 'A DELETE filter needs a Boolean or signed integer comparison.');

        return $constant;
    }
}
