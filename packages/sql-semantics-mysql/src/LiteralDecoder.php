<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use SqlParser\Lexer\Token;
use SqlSemantics\Core\Language;
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
     * Reads escapes under the configured session mode.
     */
    public function __construct(private readonly Language $language)
    {
    }

    /**
     * @param non-empty-list<Token> $tokens
     * @throws DecodingException When the value is not a literal
     */
    public function decode(array $tokens): Literal
    {
        $first = $tokens[0];
        if (count($tokens) === 1) {
            if (in_array($first->name, ['NUM', 'LONG_NUM', 'ULONGLONG_NUM', 'DECIMAL_NUM', 'FLOAT_NUM'], true)) {
                return new NumberLiteral($first->text);
            }
            if ($first->name === 'NULL_SYM') {
                return NullLiteral::Null;
            }
            if (in_array($first->name, ['TRUE_SYM', 'FALSE_SYM'], true)) {
                return new BooleanLiteral($first->name === 'TRUE_SYM');
            }
            if (in_array($first->name, ['HEX_NUM', 'BIN_NUM'], true)) {
                return new BinaryLiteral($this->bytes($first));
            }
        }
        $charset = null;
        if ($first->name === 'UNDERSCORE_CHARSET') {
            $charset = substr($first->text, 1);
            array_shift($tokens);
            if (count($tokens) === 1 && in_array($tokens[0]->name, ['HEX_NUM', 'BIN_NUM'], true)) {
                return new StringLiteral($this->bytes($tokens[0]), $charset);
            }
        }
        if ($tokens === [] || array_filter($tokens, static fn (Token $token): bool => !in_array($token->name, ['TEXT_STRING', 'NCHAR_STRING'], true)) !== []) {
            throw new DecodingException('The value is not a literal.');
        }
        $charset ??= $tokens[0]->name === 'NCHAR_STRING' ? 'utf8mb3' : null;
        return new StringLiteral(implode('', array_map($this->string(...), $tokens)), $charset);
    }

    /**
     * Decodes the validated lexical spelling without evaluating SQL.
     * @throws DecodingException When the encoding is invalid
     */
    public function bytes(Token $token): string
    {
        $digits = $token->text[0] === '0' ? substr($token->text, 2) : substr($token->text, 2, -1);
        return $token->name === 'HEX_NUM' ? Encoding::hex($digits) : Encoding::bytes($digits);
    }

    /**
     * Decodes the validated lexical spelling without evaluating SQL.
     * @throws DecodingException When the encoding is invalid
     */
    public function string(Token $token): string
    {
        $mode = $this->language->mode;
        $escapes = !$mode instanceof Mode || !$mode->sqlMode->noBackslashEscapes;
        $body = Quoted::body($token->name === 'NCHAR_STRING' ? substr($token->text, 1) : $token->text, $escapes);
        if (!$escapes) {
            return $body;
        }
        return preg_replace_callback('/\\\\(.)/s', static fn (array $match): string => match ($match[1]) {
            '0' => "\0", 'b' => "\x08", 'n' => "\n", 'r' => "\r", 't' => "\t", 'Z' => "\x1a",
            '%', '_' => '\\' . $match[1], default => $match[1],
        }, $body) ?? throw new DecodingException('Invalid escaped literal.');
    }
}
