<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema;

use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\IntegerTokenLowering;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteralLowering;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral as ParsedNull;

/**
 * Resolves the literal assignment of an ordinary native column declaration.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LiteralDefaultLowering
{
    /**
     * Builds the checked value without an analysis context.
     *
     * @throws InvalidConstruction When the operand has no supported literal role
     */
    public function lower(Scalar $operand): IntegerLiteral|RangeIntegerLiteral|TextLiteral|NullLiteral
    {
        if ($operand instanceof ParsedNull) {
            return new NullLiteral();
        }
        $text = (new TextLiteralLowering())->lower($operand);
        if ($text !== null) {
            return $text;
        }
        $token = (new IntegerTokenLowering())->lower($operand);
        Check::input($token !== null, 'The column requires a signed, text or NULL literal default.');
        [$decimal, $zeroes, $negativeZero] = $token;
        $signed = new RangeIntegerLiteral($decimal, $zeroes, $negativeZero);

        return IntegerStorage::Standard->accepts($signed) ? new IntegerLiteral($decimal, $zeroes, $negativeZero) : $signed;
    }
}
