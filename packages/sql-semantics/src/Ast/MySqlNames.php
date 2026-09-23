<?php

declare(strict_types=1);

namespace SqlSemantics\Ast;

use SqlParser\Lexer\Token;

/**
 * Decodes identifier-or-text names with MySQL lexical escaping.
 * @visibility SqlSemantics
 */
final class MySqlNames
{
    /**
     * Preserves identifier case and decodes literal bytes without evaluating SQL.
     */
    public static function read(Token $token, Identifiers $identifiers): string
    {
        if ($token->name !== 'TEXT_STRING') {
            return $identifiers->name($token);
        }
        $text = $token->text;
        $quote = $text[0];
        $name = '';
        for ($i = 1; $i < strlen($text) - 1; ++$i) {
            $character = $text[$i];
            if ($character === '\\') {
                $character = $text[++$i];
                $name .= match ($character) {
                    '0' => "\0", 'n' => "\n", 'r' => "\r", 'b' => "\x08", 't' => "\t", 'Z' => "\x1a", '%', '_' => '\\' . $character, default => $character,
                };
            } else {
                $name .= $character;
                if ($character === $quote && ($text[$i + 1] ?? '') === $quote) {
                    ++$i;
                }
            }
        }
        return $name;
    }
}
