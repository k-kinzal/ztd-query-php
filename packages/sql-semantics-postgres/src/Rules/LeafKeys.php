<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules;

use SqlParser\Lexer\Token;
use SqlSemantics\Platform\PostgreSql\Rendering\Keywords;
use SqlSemantics\Platform\PostgreSql\Rules\Lexical\Numerals;
use SqlSemantics\Platform\PostgreSql\Rules\Lexical\Strings;
use SqlSemantics\Platform\PostgreSql\Rules\Noise\AccessNoise;
use SqlSemantics\Platform\PostgreSql\Rules\Noise\CatalogNoise;
use SqlSemantics\Platform\PostgreSql\Rules\Noise\ExpressionNoise;
use SqlSemantics\Platform\PostgreSql\Rules\Noise\InvocationNoise;
use SqlSemantics\Platform\PostgreSql\Rules\Noise\LeafNoise;
use SqlSemantics\Platform\PostgreSql\Rules\Noise\ManipulationNoise;
use SqlSemantics\Platform\PostgreSql\Rules\Noise\QueryNoise;
use SqlSemantics\Platform\PostgreSql\Rules\Noise\RoutineNoise;
use SqlSemantics\Platform\PostgreSql\Rules\Noise\TableNoise;
use SqlSemantics\Platform\PostgreSql\Rules\Noise\TypesNoise;
use SqlSemantics\Platform\PostgreSql\Rules\Noise\UtilityNoise;

/**
 * Maps PostgreSQL tokens to comparison keys for the token correspondence check.
 *
 * Rule: PG-LEAF-KEY-001. An identifier, and a keyword read through one of
 * the keyword-category nonterminals, is keyed by its decoded name. A string
 * constant is keyed by its decoded value, a bit string by its notation and
 * digits, an integer constant by its value, a numeric constant (`FCONST`) by
 * its exact text, which the server keeps as the value (PG-LEX-NUMBER-001), a
 * parameter by its canonical marker,
 * an operator by its text. Every other token is keyed by its terminal; the
 * lookahead variants the parser's token filter introduces (`NOT_LA`,
 * `WITH_LA`, `WITHOUT_LA`, `NULLS_LA`, `FORMAT_LA`) are the same word as
 * their base keyword and share its key. The noise positions are the union of
 * the family tables. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class LeafKeys implements \SqlSemantics\Contract\LeafKeys
{
    /**
     * The base keyword of each lookahead terminal.
     */
    private const LOOKAHEAD = ['NOT_LA' => 'NOT', 'WITH_LA' => 'WITH', 'WITHOUT_LA' => 'WITHOUT', 'NULLS_LA' => 'NULLS_P', 'FORMAT_LA' => 'FORMAT'];

    /**
     * @var array<string, list<int>>|null
     */
    private static ?array $noise = null;

    /**
     * Answers the comparison key of a token, or null when the token is declared noise.
     */
    public function key(Token $token, string $signature, int $position): ?string
    {
        if ($token->text === '' || in_array($position, $this->noise()[$signature] ?? [], true)) {
            return null;
        }
        if (in_array(substr($signature, 0, (int) strpos($signature, ':')), Keywords::CATEGORIES, true)) {
            return 'name:' . (new Identifiers())->fold($token->text);
        }

        return match ($token->name) {
            'IDENT' => 'name:' . (new Identifiers())->decode($token->text),
            'SCONST' => 'string:' . (new Strings())->decode($token->text),
            'BCONST' => 'bits:b' . (new Strings())->digits($token->text),
            'XCONST' => 'bits:x' . (new Strings())->digits($token->text),
            'ICONST' => 'number:' . (new Numerals())->decimal($token->text),
            'FCONST' => 'float:' . $token->text,
            'PARAM' => 'param:' . (str_starts_with($token->text, '$') ? '$' . (new Numerals())->canonical(substr($token->text, 1)) : $token->text),
            'Op' => 'op:' . $token->text,
            default => self::LOOKAHEAD[$token->name] ?? $token->name,
        };
    }

    /**
     * Answers the noise positions of every family by production signature.
     *
     * @return array<string, list<int>>
     */
    public function noise(): array
    {
        return self::$noise ??= LeafNoise::positions() + TypesNoise::positions() + ExpressionNoise::positions() + InvocationNoise::positions()
            + QueryNoise::positions() + ManipulationNoise::positions() + TableNoise::positions() + CatalogNoise::positions()
            + RoutineNoise::positions() + AccessNoise::positions() + UtilityNoise::positions();
    }
}
