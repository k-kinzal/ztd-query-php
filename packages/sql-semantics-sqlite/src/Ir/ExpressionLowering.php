<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Statement\Expression\BindParameter;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral as ParsedInteger;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ParameterPrefix;

/**
 * Translates the supported analyzed operands into a closed SQLite IR.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ExpressionLowering
{
    /**
     * Converts a supported expression and resolves its column references.
     *
     * @throws InvalidConstruction When the expression is unsupported or a reference is unresolved
     */
    public function lower(Scalar $expression, SourceBinding $binding): Arithmetic|ColumnReference|IntegerLiteral|NullLiteral|Parameter
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if ($expression instanceof Binary) {
            $operator = ArithmeticOperator::tryFrom($expression->operator->value);
            Check::input($operator !== null, 'This operation has no complete SQLite projection representation.');

            return new Arithmetic($operator, $this->lower($expression->left, $binding), $this->lower($expression->right, $binding));
        }
        if ($expression instanceof ParsedInteger) {
            $digits = ltrim($expression->digits, '0');

            return new IntegerLiteral($digits === '' ? '0' : $digits);
        }
        if ($expression instanceof Unary && $expression->operator === UnaryOperator::Minus && $expression->operand instanceof ParsedInteger) {
            $digits = ltrim($expression->operand->digits, '0');

            return new IntegerLiteral($digits === '' ? '0' : '-' . $digits);
        }

        return match (true) {
            $expression instanceof ParsedNull => new NullLiteral(),
            $expression instanceof BindParameter && $expression->prefix === ParameterPrefix::Question => new Parameter($expression->label),
            $expression instanceof ColumnUse, $expression instanceof DoubleQuotedWord => $binding->reference($expression),
            default => throw new InvalidConstruction('This expression has no complete SQLite projection representation.'),
        };
    }
}
