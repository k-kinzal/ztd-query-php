<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Ir\Expression\BinaryOperatorLowering;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseBranch;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastTarget;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\NumericLiteralLowering;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Statement\Expression\BindParameter;
use SqlSemantics\Platform\Sqlite\Statement\Expression\CaseExpression as ParsedCase;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Cast as ParsedCast;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\BlobLiteral as ParsedBlob;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\HexLiteral as ParsedHex;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral as ParsedInteger;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral as ParsedReal;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral as ParsedText;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ParameterPrefix;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;

/**
 * Resolves the closed scalar expressions used by queries and writes.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ExpressionLowering
{
    /**
     * Converts a scalar expression and resolves its column references.
     *
     * @throws InvalidConstruction When the expression is unsupported or a reference is unresolved
     */
    public function lower(Scalar $expression, SourceBinding $binding): Arithmetic|BlobLiteral|CaseExpression|CastExpression|ColumnReference|Comparison|HexIntegerLiteral|IntegerLiteral|Logical|NullLiteral|Parameter|RealLiteral|TextLiteral
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }

        if ($expression instanceof Binary) {
            $operator = (new BinaryOperatorLowering())->lower($expression->operator);
            $left = $this->lower($expression->left, $binding);
            $right = $this->lower($expression->right, $binding);

            return match (true) {
                $operator instanceof ArithmeticOperator => new Arithmetic($operator, $left, $right),
                $operator instanceof ComparisonOperator => new Comparison($operator, $left, $right),
                $operator instanceof LogicalOperator => new Logical($operator, $left, $right),
            };
        }
        if ($expression instanceof Unary) {
            return (new NumericLiteralLowering())->signed($expression);
        }

        return match (true) {
            $expression instanceof ParsedCase => $this->choice($expression, $binding),
            $expression instanceof ParsedCast => $this->cast($expression, $binding),
            $expression instanceof ParsedInteger, $expression instanceof ParsedHex, $expression instanceof ParsedReal => (new NumericLiteralLowering())->lower($expression, false),
            $expression instanceof ParsedText => new TextLiteral($expression->value),
            $expression instanceof ParsedBlob => new BlobLiteral($expression->hex),
            $expression instanceof ParsedNull => new NullLiteral(),
            $expression instanceof BindParameter && $expression->prefix === ParameterPrefix::Question => new Parameter($expression->label),
            $expression instanceof ColumnUse, $expression instanceof DoubleQuotedWord => $binding->reference($expression),
            default => throw new InvalidConstruction('This expression has no complete SQLite scalar representation.'),
        };
    }

    /**
     * Maps each checked conditional operand in its written order.
     *
     * @throws InvalidConstruction When a conditional operand is unsupported or a reference is unresolved
     */
    public function choice(ParsedCase $expression, SourceBinding $binding): CaseExpression
    {
        $branches = [];
        foreach ($expression->branches as $branch) {
            $branches[] = new CaseBranch($this->lower($branch->when, $binding), $this->lower($branch->then, $binding));
        }

        return new CaseExpression(
            $expression->base === null ? null : $this->lower($expression->base, $binding),
            $branches,
            $expression->otherwise === null ? null : $this->lower($expression->otherwise, $binding),
        );
    }

    /**
     * Resolves the written type name to the effective SQLite cast affinity.
     *
     * @throws InvalidConstruction When the operand has no complete scalar representation
     */
    public function cast(ParsedCast $expression, SourceBinding $binding): CastExpression
    {
        $target = match ($expression->target?->affinity() ?? Affinity::Numeric) {
            Affinity::Integer => CastTarget::Integer,
            Affinity::Real => CastTarget::Real,
            Affinity::Numeric => CastTarget::Numeric,
            Affinity::Text => CastTarget::Text,
            Affinity::Blob => CastTarget::Blob,
        };

        return new CastExpression($target, $this->lower($expression->operand, $binding));
    }
}
