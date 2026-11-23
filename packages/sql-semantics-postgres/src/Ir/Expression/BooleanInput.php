<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Expression;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;

/**
 * Decodes the stock Boolean input in a context that has selected that type.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class BooleanInput
{
    /**
     * Decodes an unknown string literal, including a grouped literal.
     *
     * @throws InvalidConstruction When the Boolean input is invalid
     */
    public function untyped(Scalar $operand): ?BooleanLiteral
    {
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        if ($operand instanceof Constant && $operand->value instanceof StringConstant) {
            return $this->fromText($operand->value->value);
        }

        return null;
    }

    /**
     * Decodes the unambiguous Boolean tokens after ASCII trim and case folding.
     *
     * @throws InvalidConstruction When the Boolean input is invalid
     */
    public function fromText(string $value, string $diagnostic = 'The Boolean input contains an invalid token.'): BooleanLiteral
    {
        $token = strtr(trim($value, " \t\n\r\f\v"), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');

        return new BooleanLiteral(match ($token) {
            't', 'tr', 'tru', 'true', 'y', 'ye', 'yes', 'on', '1' => true,
            'f', 'fa', 'fal', 'fals', 'false', 'n', 'no', 'of', 'off', '0' => false,
            default => throw new InvalidConstruction($diagnostic),
        });
    }
}
