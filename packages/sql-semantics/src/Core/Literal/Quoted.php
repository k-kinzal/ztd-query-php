<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Literal;

/**
 * Removes quote doubling and joins lexically validated string continuations.
 * @visibility SqlSemantics
 */
final class Quoted
{
    /**
     * Unquotes one token, preserving backslash escapes for the dialect decoder.
     * @throws DecodingException When the quotes or continuations are invalid
     */
    public static function body(string $text, bool $backslash = false): string
    {
        $quote = $text[0] ?? '';
        if (!in_array($quote, ["'", '"'], true)) {
            throw new DecodingException('Expected a quoted string.');
        }
        $result = '';
        for ($i = 1; $i < strlen($text); ++$i) {
            $char = $text[$i];
            if ($backslash && $char === '\\' && isset($text[$i + 1])) {
                $result .= $char . $text[++$i];
            } elseif ($char !== $quote) {
                $result .= $char;
            } elseif (($text[$i + 1] ?? '') === $quote) {
                $result .= $quote;
                ++$i;
            } elseif ($i === strlen($text) - 1) {
                return $result;
            } else {
                if (preg_match('/\G(?:\s+|--[^\r\n]*)+\x27/', $text, $match, 0, $i + 1) !== 1) {
                    throw new DecodingException('Invalid string continuation.');
                }
                $i += strlen($match[0]);
            }
        }
        throw new DecodingException('Unterminated string.');
    }
}
