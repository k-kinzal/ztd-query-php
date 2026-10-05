<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules;

use SqlParser\Lexer\Token;

/**
 * Keys SQLite tokens for the token correspondence check.
 *
 * Rule: SQLITE-LEAF-KEYS-001. A token at an identifier position is keyed by
 * its decoded name, so equivalent spellings of one name are equal. An
 * unqualified word used as a value is keyed apart when it is written in
 * double quotes or is the bare word TRUE or FALSE, because those are other
 * requests than a column use. A word before JOIN that is written as a join
 * keyword is keyed as that keyword. A string is keyed by its decoded text, a
 * BLOB by its hexadecimal digits, a number by its spelling without digit
 * separators and without regard to case, a bind parameter by its exact text.
 * Every other token is keyed by its terminal and its text without regard to
 * ASCII case, so `=` and `==` stay different. The positions and synonyms of
 * Noise are the only exceptions. The keys of the words before JOIN depend on
 * the production read just before them, so one instance keys one token
 * sequence at a time, in order. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class LeafKeys implements \SqlSemantics\Contract\LeafKeys
{
    /**
     * The grammar symbols at which a token is an identifier.
     */
    private const IDENTIFIERS = ['idj', 'ids', 'id', 'nm: STRING'];

    /**
     * @var array<string, list<string>>
     */
    private array $symbols = [];

    private int $joinWords = 0;

    /**
     * Answers the comparison key of a token, or null for declared noise.
     */
    public function key(Token $token, string $signature, int $position): ?string
    {
        if ($token->text === '' || in_array($position, Noise::positions()[$signature] ?? DefinitionNoise::positions()[$signature] ?? [], true)) {
            return null;
        }
        if (str_starts_with($signature, 'joinop: ')) {
            $this->joinWords = $position === 0 ? substr_count($signature, ' nm') : $this->joinWords;
        } elseif ($signature === 'nm: idj' && $this->joinWords > 0) {
            $this->joinWords--;
            if ($token->name === 'JOIN_KW') {
                return 'JOIN_KW:' . strtoupper($token->text);
            }
        }
        if ($signature === 'expr: idj') {
            return $this->word($token);
        }
        $this->symbols[$signature] ??= explode(' ', substr($signature, (int) strpos($signature, ':') + 1 + (str_ends_with($signature, ':') ? 0 : 1)));
        $symbol = $this->symbols[$signature][$position] ?? '';
        if (in_array($symbol, self::IDENTIFIERS, true) || in_array($signature, self::IDENTIFIERS, true)) {
            return 'name:' . (new Identifiers())->decode($token->text);
        }

        return $this->literal($token);
    }

    /**
     * Keys an unqualified word used as a value by the request it is.
     */
    public function word(Token $token): string
    {
        $decoded = (new Identifiers())->decode($token->text);
        if (str_starts_with($token->text, '"')) {
            return 'quoted:' . $decoded;
        }

        return in_array(strtolower($token->text), ['true', 'false'], true) ? 'truth:' . strtolower($token->text) : 'name:' . $decoded;
    }

    /**
     * Keys a token that is no identifier: a literal by its value, a keyword or punctuation by its terminal and text.
     */
    public function literal(Token $token): string
    {
        return match ($token->name) {
            'STRING' => 'string:' . (new Identifiers())->decode($token->text),
            'BLOB' => 'blob:' . strtoupper(substr($token->text, 2, -1)),
            'INTEGER', 'FLOAT', 'QNUMBER' => 'number:' . strtoupper(str_replace('_', '', $token->text)),
            'VARIABLE' => 'parameter:' . $token->text,
            default => $token->name . ':' . (Noise::synonyms()[$token->name] ?? strtr($token->text, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')),
        };
    }

    /**
     * Tells whether a written token is the same terminal as the rendered one.
     */
    public function synonymous(Token $rendered, Token $written): bool
    {
        return $rendered->name === $written->name;
    }
}
