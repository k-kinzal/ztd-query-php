<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use SqlParser\Lexer\Token;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Literal\DecodingException;
use SqlSemantics\Core\Literal\Encoding;
use SqlSemantics\Core\Literal\Quoted;
use SqlSemantics\Core\Policy\LiteralRules;
use SqlSemantics\Platform\PostgreSql\Literal\Escapes;
use SqlSemantics\Statement\Literal\BitLiteral;
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
     * Reads combined Unicode tokens with the selected release's lexer.
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
        if (count($tokens) !== 1) {
            throw new DecodingException('The value is not a literal.');
        }
        return match ($first->name) {
            'SCONST' => new StringLiteral($this->string($first->text)),
            'BCONST' => new BitLiteral(Encoding::bits(Quoted::body(substr($first->text, 1)))),
            'XCONST' => new BitLiteral(Encoding::bits(Quoted::body(substr($first->text, 1)), true)),
            'ICONST', 'FCONST' => new NumberLiteral(Encoding::number($first->text)),
            'TRUE_P', 'FALSE_P' => new BooleanLiteral($first->name === 'TRUE_P'),
            'NULL_P' => NullLiteral::Null,
            default => throw new DecodingException('The value is not a literal.'),
        };
    }

    /**
     * Decodes the validated lexical spelling without evaluating SQL.
     * @throws DecodingException When the encoding is invalid
     */
    public function string(string $text): string
    {
        if ($text[0] === '$') {
            $end = strpos($text, '$', 1);
            if ($end === false) {
                throw new DecodingException('Invalid dollar string.');
            }
            $body = substr($text, $end + 1, -($end + 1));
            if (str_contains($body, "\0")) {
                throw new DecodingException('A text literal cannot contain a zero byte.');
            }
            return $body;
        }
        if (strtoupper(substr($text, 0, 2)) === 'U&') {
            return $this->unicode($text);
        }
        $escaped = strtoupper($text[0]) === 'E';
        $body = Quoted::body($escaped ? substr($text, 1) : $text, $escaped);
        $decoded = $escaped ? (new Escapes())->cStyle($body) : $body;
        if (str_contains($decoded, "\0")) {
            throw new DecodingException('A text literal cannot contain a zero byte.');
        }
        return $decoded;
    }

    /**
     * Uses the lexer to separate a Unicode token's optional UESCAPE clause.
     * @throws DecodingException When the Unicode encoding is invalid
     */
    public function unicode(string $text): string
    {
        $tokens = array_values(array_filter($this->language->parser()->tokenize(substr($text, 2)), static fn (Token $token): bool => $token->text !== ''));
        if (count($tokens) !== 1 && count($tokens) !== 3) {
            throw new DecodingException('Invalid Unicode string.');
        }
        $escape = count($tokens) === 3 ? Quoted::body($tokens[2]->text) : '\\';
        return (new Escapes())->unicode(Quoted::body($tokens[0]->text), $escape);
    }
}
