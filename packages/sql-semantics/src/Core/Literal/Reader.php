<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Literal;

use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlSemantics\Core\Language;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Literal\Literal;
use SqlSemantics\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Writer;

/**
 * Decodes a typed value under the same language and mode that read it.
 * @visibility SqlSemantics
 */
final class Reader
{
    /**
     * Uses the resolved language for every read.
     */
    public function __construct(private readonly Language $language)
    {
    }

    /**
     * @throws DecodingException When the value requires expression evaluation
     */
    public function read(Element $value): Literal
    {
        try {
            $tokens = array_values(array_filter($this->language->parser()->tokenize(Writer::render($value)), static fn (Token $token): bool => $token->text !== ''));
        } catch (SourceException $error) {
            throw new DecodingException($error->getMessage(), 0, $error);
        }
        return $this->decode($tokens);
    }

    /**
     * @param list<Token> $tokens
     * @throws DecodingException When the tokens require evaluation
     */
    public function decode(array $tokens): Literal
    {
        if ($tokens === []) {
            throw new DecodingException('An empty value is not a literal.');
        }
        if ($this->parenthesized($tokens)) {
            return $this->decode(array_slice($tokens, 1, -1));
        }
        if (in_array($tokens[0]->text, ['+', '-'], true)) {
            $operand = $this->decode(array_slice($tokens, 1));
            if (!$operand instanceof NumberLiteral) {
                throw new DecodingException('A signed literal needs a number; no coercion is performed.');
            }
            $text = $operand->value;
            return new NumberLiteral($tokens[0]->text === '+' ? $text : (str_starts_with($text, '-') ? substr($text, 1) : '-' . $text));
        }
        return $this->language->dialect->platform()->literals($this->language)->decode($tokens);
    }

    /**
     * @param non-empty-list<Token> $tokens
     */
    public function parenthesized(array $tokens): bool
    {
        if ($tokens[0]->text !== '(' || $tokens[count($tokens) - 1]->text !== ')') {
            return false;
        }
        $depth = 0;
        foreach ($tokens as $index => $token) {
            $depth += $token->text === '(' ? 1 : ($token->text === ')' ? -1 : 0);
            if ($depth === 0 && $index !== count($tokens) - 1) {
                return false;
            }
        }
        return $depth === 0;
    }
}
