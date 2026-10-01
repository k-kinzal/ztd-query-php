<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis;

use SqlParser\Lexer\Token;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Platform\Sqlite\LiteralDecoder;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Expression\SqliteBlob;
use SqlSemantics\Statement\Expression\SqliteCurrentTime;
use SqlSemantics\Statement\Expression\SqliteInteger;
use SqlSemantics\Statement\Expression\SqliteReal;
use SqlSemantics\Statement\Expression\SqliteText;
use SqlSemantics\Statement\Literal\BinaryLiteral;
use SqlSemantics\Statement\Literal\Radix;
use SqlSemantics\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Literal\UnsignedInteger;

/**
 * Decodes expression constants into values with their SQLite result domains.
 * @visibility SqlSemantics
 */
final class LiteralExpressionReader
{
    /**
     * The parser's terminal kind selects a concrete literal interpretation.
     */
    public function read(Token $token): ScalarExpression
    {
        if ($token->name === 'NULL') {
            return new NullConstant($token->text);
        }
        if ($token->name === 'CTIME_KW') {
            return SqliteCurrentTime::from(strtoupper($token->text));
        }
        if (in_array($token->name, ['INTEGER', 'FLOAT', 'QNUMBER'], true)) {
            $text = $token->text;
            $hexadecimal = str_starts_with(strtolower($text), '0x');
            if ($hexadecimal || ctype_digit(str_replace('_', '', $text))) {
                return new SqliteInteger(new UnsignedInteger($hexadecimal ? substr($text, 2) : $text, $hexadecimal ? Radix::Hexadecimal : Radix::Decimal), uppercasePrefix: str_starts_with($text, '0X'));
            }
            return new SqliteReal($text);
        }
        if ($token->name === 'STRING') {
            $value = (new LiteralDecoder())->decode([$token]);
            assert($value instanceof StringLiteral, 'A STRING terminal decodes to a text value.');
            return new SqliteText($value);
        }
        if ($token->name === 'BLOB') {
            $value = (new LiteralDecoder())->decode([$token]);
            assert($value instanceof BinaryLiteral, 'A BLOB terminal decodes to a binary value.');
            return new SqliteBlob($value, $token->text[0] === 'x', substr($token->text, 2, -1));
        }
        Tree::unsupported($token, 'literal expression');
    }
}
