<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Concatenation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\ExpressionLowering;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\NumericLiteralLowering;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\HexLiteral as ParsedHex;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral as ParsedInteger;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral as ParsedReal;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultExpression;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultWord;
use SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign;

/**
 * Lowers a column default to a literal or closed computed role.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class DefaultLowering
{
    /**
     * Resolves one default before its column enters the complete value.
     *
     * @throws InvalidConstruction When the default has an unrepresented or unbound operand
     */
    public function lower(
        DefaultExpression|DefaultLiteral|DefaultWord $constraint,
        BoundStatement $operation,
    ): BlobLiteral|ColumnDefaultExpression|HexIntegerLiteral|IntegerLiteral|NullLiteral|RealLiteral|TextLiteral {
        if ($constraint instanceof DefaultWord) {
            $truth = $constraint->truth();

            return $truth === null ? new TextLiteral($constraint->word->name->value) : new IntegerLiteral((string) (int) $truth);
        }
        $scalar = $constraint instanceof DefaultLiteral ? $constraint->literal : $constraint->expression;
        if ($constraint instanceof DefaultLiteral && $constraint->sign !== null) {
            Check::input($scalar instanceof ParsedInteger || $scalar instanceof ParsedHex || $scalar instanceof ParsedReal, 'A signed default requires a numeric literal.');

            return (new NumericLiteralLowering())->lower($scalar, $constraint->sign === NumberSign::Minus);
        }

        $default = (new ExpressionLowering())->lower($scalar, new SourceBinding($operation, null));
        if ($default instanceof Arithmetic || $default instanceof CaseExpression || $default instanceof CastExpression
            || $default instanceof Coalesce || $default instanceof Concatenation || $default instanceof FoldedMembership
            || $default instanceof UnaryExpression) {
            return new ColumnDefaultExpression($default);
        }
        Check::input(
            $default instanceof BlobLiteral || $default instanceof HexIntegerLiteral || $default instanceof IntegerLiteral
            || $default instanceof NullLiteral || $default instanceof RealLiteral || $default instanceof TextLiteral,
            'A checked column default requires a closed literal or computed operand.',
        );

        return $default;
    }
}
