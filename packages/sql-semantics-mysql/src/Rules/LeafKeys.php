<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules;

use SqlParser\Lexer\Token;
use SqlSemantics\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Rules\Noise\AccountNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\CallNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\DispatchNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\DmlNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\ExpressionNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\LeafNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\QueryNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\ReplicationNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\RoutineNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\ServerNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\TableChangeNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\TableDefinitionNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\TypeNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\UtilityNoise;

/**
 * Keys MySQL tokens for the token correspondence check.
 *
 * Rule: MYSQL-LEAF-KEYS-001. A keyword or punctuation token is keyed by its
 * terminal: the server grammar sees only the terminal, so the words the
 * lexer maps to one terminal (`INT`/`INTEGER`, `DATABASE`/`SCHEMA`,
 * `COLUMNS`/`FIELDS`, `<>`/`!=`, `DESCRIBE`/`EXPLAIN`) are one request. An
 * identifier token, a keyword at a keyword-as-identifier production, a string
 * at a production that reads it as a name, and a host name are keyed by the
 * decoded name. A string literal is keyed by its decoded bytes under the
 * escape setting of the profile, a number by its exact text, a hexadecimal
 * or bit literal by its digits whatever its spelling, an introducer by its
 * character set, a parameter marker by its text. A position a family noise
 * table lists has no key; a position a family synonym table lists has the
 * key that table states, so two distinct terminals the manual defines as
 * synonyms compare equal. The end marker has no text and no key.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LeafKeys implements \SqlSemantics\Contract\LeafKeys
{
    /**
     * The rules whose single token is a name whatever its terminal.
     */
    private const NAME_RULES = [
        'IDENT_sys' => true, 'keyword' => true, 'keyword_sp' => true, 'ident_keywords_unambiguous' => true,
        'ident_keywords_ambiguous_1_roles_and_labels' => true, 'ident_keywords_ambiguous_2_labels' => true,
        'ident_keywords_ambiguous_3_roles' => true, 'ident_keywords_ambiguous_4_system_variables' => true,
        'TEXT_STRING_sys' => true, 'TEXT_STRING_validated' => true,
    ];

    /**
     * @var array<string, list<int>>|null
     */
    private static ?array $noise = null;

    /**
     * @var array<string, array<int, string>>|null
     */
    private static ?array $synonyms = null;

    /**
     * @param LexicalSettings $lexical The lexical settings strings are decoded under
     */
    public function __construct(private readonly LexicalSettings $lexical)
    {
    }

    /**
     * Answers the comparison key of a token, or null for declared noise.
     */
    public function key(Token $token, string $signature, int $position): ?string
    {
        if ($token->text === '' || in_array($position, self::noise()[$signature] ?? [], true)) {
            return null;
        }
        $synonym = self::synonyms()[$signature][$position] ?? null;
        if ($synonym !== null) {
            return $synonym;
        }
        if (isset(self::NAME_RULES[substr($signature, 0, (int) strpos($signature, ':'))])) {
            return 'name:' . $this->name($token);
        }

        return $this->terminal($token);
    }

    /**
     * Decodes the name a token denotes at a name production.
     */
    public function name(Token $token): string
    {
        return match ($token->name) {
            'IDENT', 'IDENT_QUOTED' => (new Identifiers())->decode($token->text),
            'TEXT_STRING' => (new Strings())->decode($token->text, !$this->lexical->noBackslashEscapes),
            default => $token->text,
        };
    }

    /**
     * Keys a token by what its terminal denotes outside the name productions.
     */
    public function terminal(Token $token): string
    {
        return match ($token->name) {
            'IDENT', 'IDENT_QUOTED' => 'name:' . (new Identifiers())->decode($token->text),
            'LEX_HOSTNAME' => 'name:' . $token->text,
            'TEXT_STRING' => 'text:' . (new Strings())->decode($token->text, !$this->lexical->noBackslashEscapes),
            'NCHAR_STRING' => 'national:' . (new Strings())->decode($token->text, !$this->lexical->noBackslashEscapes),
            'UNDERSCORE_CHARSET' => 'charset:' . (new Introducers())->charset($token->text),
            'HEX_NUM' => 'hex:' . strtr((new RadixSpelling())->digits($token->text), 'ABCDEF', 'abcdef'),
            'BIN_NUM' => 'bit:' . (new RadixSpelling())->digits($token->text),
            'NUM', 'LONG_NUM', 'ULONGLONG_NUM', 'DECIMAL_NUM', 'FLOAT_NUM' => 'number:' . $token->text,
            'PARAM_MARKER' => 'parameter:' . $token->text,
            'DOLLAR_QUOTED_STRING_SYM' => 'dollar:' . $token->text,
            default => $token->name,
        };
    }

    /**
     * Answers the noise positions of every family by production signature.
     *
     * @return array<string, list<int>>
     */
    public static function noise(): array
    {
        return self::$noise ??= DispatchNoise::positions() + LeafNoise::positions() + TypeNoise::positions() + ExpressionNoise::positions()
            + CallNoise::positions() + QueryNoise::positions() + DmlNoise::positions() + TableDefinitionNoise::positions()
            + TableChangeNoise::positions() + RoutineNoise::positions() + AccountNoise::positions() + ReplicationNoise::positions()
            + ServerNoise::positions() + UtilityNoise::positions();
    }

    /**
     * Answers the synonym keys of every family by production signature and position.
     *
     * @return array<string, array<int, string>>
     */
    public static function synonyms(): array
    {
        return self::$synonyms ??= DispatchNoise::synonyms() + LeafNoise::synonyms() + TypeNoise::synonyms() + ExpressionNoise::synonyms()
            + CallNoise::synonyms() + QueryNoise::synonyms() + DmlNoise::synonyms() + TableDefinitionNoise::synonyms()
            + TableChangeNoise::synonyms() + RoutineNoise::synonyms() + AccountNoise::synonyms() + ReplicationNoise::synonyms()
            + ServerNoise::synonyms() + UtilityNoise::synonyms();
    }
}
