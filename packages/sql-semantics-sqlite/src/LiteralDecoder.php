<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use SqlParser\Lexer\Token;
use SqlSemantics\Core\Literal\DecodingException;
use SqlSemantics\Core\Literal\Encoding;
use SqlSemantics\Core\Literal\Quoted;
use SqlSemantics\Core\Policy\LiteralRules;
use SqlSemantics\Statement\Literal\BinaryLiteral;
use SqlSemantics\Statement\Literal\BooleanLiteral;
use SqlSemantics\Statement\Literal\Literal;
use SqlSemantics\Statement\Literal\NullLiteral;
use SqlSemantics\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Literal\StringLiteral;

/**
 * Decodes literal syntax without evaluating expressions or converting to a column type.
 * @visibility SqlSemantics
 */
final class LiteralDecoder implements LiteralRules
{
    /**
     * @param non-empty-list<Token> $tokens
     * @throws DecodingException When the value is not a literal
     */
    public function decode(array $tokens): Literal
    {
        if (count($tokens) !== 1) {
            throw new DecodingException('The value is not a literal.');
        }
        $token = $tokens[0];
        if ($token->name === 'ID' && in_array(\SqlSemantics\Statement\Identifier\Ascii::upper($token->text), ['TRUE', 'FALSE'], true)) {
            return new BooleanLiteral(\SqlSemantics\Statement\Identifier\Ascii::upper($token->text) === 'TRUE');
        }
        return match ($token->name) {
            'STRING' => new StringLiteral(Quoted::body($token->text)),
            'BLOB' => new BinaryLiteral(Encoding::hex(substr($token->text, 2, -1))),
            'NULL' => NullLiteral::Null,
            'TRUEFALSE' => new BooleanLiteral(\SqlSemantics\Statement\Identifier\Ascii::upper($token->text) === 'TRUE'),
            'INTEGER', 'FLOAT', 'QNUMBER' => new NumberLiteral($this->number($token->text)),
            default => throw new DecodingException('The value is not a literal.'),
        };
    }

    /**
     * Decodes the validated lexical spelling without evaluating SQL.
     * @throws DecodingException When the encoding is invalid
     */
    public function number(string $text): string
    {
        $text = str_replace('_', '', $text);
        if (\SqlSemantics\Statement\Identifier\Ascii::lower(substr($text, 0, 2)) !== '0x') {
            return $text;
        }
        $hex = ltrim(substr($text, 2), '0');
        if (strlen($hex) > 16) {
            throw new DecodingException('A hexadecimal integer must fit in 64 bits.');
        }
        if (strlen($hex) === 16 && hexdec($hex[0]) >= 8) {
            $complement = implode('', array_map(static fn (string $digit): string => dechex(15 - (int) hexdec($digit)), str_split($hex)));
            $magnitude = Encoding::number('0x' . $complement);
            return '-' . Encoding::successor($magnitude);
        }
        return Encoding::number($text);
    }
}
