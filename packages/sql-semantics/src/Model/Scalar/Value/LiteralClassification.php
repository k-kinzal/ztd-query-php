<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Value;

use SqlSemantics\Model\Validation\InvalidStructure;

/**

 * Classifies one literal spelling without evaluating its runtime value. @visibility SqlSemantics

 */
final class LiteralClassification
{
    /**
     * @throws InvalidStructure
     */
    public static function of(string $text, ?\SqlSemantics\Dialect $dialect = null): LiteralKind
    {
        $upper = strtoupper($text);
        if ($upper === 'NULL') {
            return LiteralKind::Null;
        }
        if ($upper === 'TRUE' || $upper === 'FALSE') {
            return LiteralKind::Boolean;
        }
        if ($dialect === \SqlSemantics\Dialect::MySql && preg_match('/^0[xX][0-9a-fA-F]+$/D', $text) === 1) {
            return LiteralKind::Binary;
        }
        if ($dialect === \SqlSemantics\Dialect::MySql && preg_match('/^0[bB][01]+$/D', $text) === 1) {
            return LiteralKind::BitString;
        }
        if (preg_match('/^[+-]?(?:(?:[0-9_]+(?:\.[0-9_]*)?|\.[0-9_]+)(?:[Ee][+-]?[0-9_]+)?|0[xX][0-9a-fA-F_]+|0[bB][01_]+|0[oO][0-7_]+)$/D', $text) === 1) {
            return LiteralKind::Number;
        }
        if (preg_match('/^[bB]\'[01]*\'$/D', $text) === 1) {
            return LiteralKind::BitString;
        }
        if (preg_match('/^[xX]\'[0-9a-fA-F]*\'$/D', $text) === 1) {
            return LiteralKind::Binary;
        }
        if ($dialect !== null && LiteralText::accepts($text, $dialect)) {
            return LiteralKind::Text;
        }
        throw new InvalidStructure('A literal must contain exactly one classified SQL value.');
    }
}
