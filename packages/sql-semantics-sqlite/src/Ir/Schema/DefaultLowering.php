<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\ExpressionLowering;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\NumericLiteralLowering;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
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
 * Lowers a checked column default to its literal storage role.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class DefaultLowering
{
    /**
     * Resolves one default before its column enters the complete value.
     *
     * @throws InvalidConstruction When the default is outside the literal roles
     */
    public function lower(
        DefaultExpression|DefaultLiteral|DefaultWord $constraint,
        BoundStatement $operation,
    ): BlobLiteral|HexIntegerLiteral|IntegerLiteral|NullLiteral|RealLiteral|TextLiteral {
        if ($constraint instanceof DefaultWord) {
            $truth = $constraint->truth();

            return $truth === null ? new TextLiteral($constraint->word->name->value) : new IntegerLiteral($truth ? '1' : '0');
        }
        $scalar = $constraint instanceof DefaultLiteral ? $constraint->literal : $constraint->expression;
        if ($constraint instanceof DefaultLiteral && $constraint->sign !== null) {
            Check::input($scalar instanceof ParsedInteger || $scalar instanceof ParsedHex || $scalar instanceof ParsedReal, 'A signed default requires a numeric literal.');

            return (new NumericLiteralLowering())->lower($scalar, $constraint->sign === NumberSign::Minus);
        }
        $default = (new ExpressionLowering())->lower($scalar, new SourceBinding($operation, null));
        Check::input(
            $default instanceof BlobLiteral || $default instanceof HexIntegerLiteral || $default instanceof IntegerLiteral
            || $default instanceof NullLiteral || $default instanceof RealLiteral || $default instanceof TextLiteral,
            'A checked column default requires one closed literal operand.',
        );

        return $default;
    }
}
