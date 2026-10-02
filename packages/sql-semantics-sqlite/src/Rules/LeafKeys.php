<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules;

use SqlParser\Lexer\Token;

/**
 * Keys SQLite tokens for the token correspondence check.
 *
 * Rule: SQLITE-LEAF-KEYS-001. A token at an identifier position is keyed by
 * its decoded name, so equivalent spellings of one name are equal. Every other
 * token is keyed by its terminal and its text without regard to ASCII case,
 * so `=` and `==` stay different. The positions of Noise have no key.
 * Status: Implemented.
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

    /**
     * Answers the comparison key of a token, or null for declared noise.
     */
    public function key(Token $token, string $signature, int $position): ?string
    {
        if ($token->text === '' || in_array($position, Noise::positions()[$signature] ?? DefinitionNoise::positions()[$signature] ?? [], true)) {
            return null;
        }
        $this->symbols[$signature] ??= explode(' ', substr($signature, (int) strpos($signature, ':') + 1 + (str_ends_with($signature, ':') ? 0 : 1)));
        $symbol = $this->symbols[$signature][$position] ?? '';
        if (in_array($symbol, self::IDENTIFIERS, true) || in_array($signature, self::IDENTIFIERS, true)) {
            return 'name:' . (new Identifiers())->decode($token->text);
        }

        return $token->name . ':' . strtr($token->text, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
    }
}
