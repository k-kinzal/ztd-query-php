<?php

declare(strict_types=1);

namespace SqlFormatter\Compact;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Recognizes optional syntax by its grammar owner rather than neighboring words.
 *
 * @visibility SqlFormatter
 */
final class Rules
{
    /**
     * Removes defaults only from the rules in which omission has the same meaning.
     *
     * @param list<Token> $tokens
     * @return list<Token>
     */
    public static function apply(Node $node, array $tokens, ?Node $parent): array
    {
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), $tokens);
        if (self::default($node->name, $words, $parent)) {
            return [];
        }
        if ($node->name === 'select_options' && !in_array('DISTINCT', $words, true)) {
            return array_values(array_filter($tokens, static fn (Token $token): bool => $token->name !== 'ALL'));
        }
        if (in_array($node->name, ['normal_join', 'inner_join_type', 'outer_join_type', 'natural_join_type', 'join_type', 'joinop'], true)) {
            if ($node->name !== 'joinop' || preg_match('/^(?:NATURAL )?(?:INNER|(?:LEFT|RIGHT|FULL)(?: OUTER)?) JOIN$/D', implode(' ', $words)) === 1) {
                return array_values(array_filter($tokens, static fn (Token $token): bool => !in_array(strtoupper($token->text), ['INNER', 'OUTER'], true)));
            }
        }
        return $tokens;
    }

    /**
     * @param list<string> $words
     */
    public static function default(string $rule, array $words, ?Node $parent): bool
    {
        if ($words === ['OUTER'] && $rule === 'opt_outer') {
            return true;
        }
        if ($words === ['ALL'] && in_array($rule, ['opt_all_clause', 'distinct'], true)) {
            return true;
        }
        if ($words === ['ASC'] && in_array($rule, ['order_dir', 'ordering_direction', 'opt_ordering_direction', 'opt_asc_desc', 'sortorder'], true)) {
            return true;
        }
        return $words === ['DISTINCT'] && ($rule === 'union_option'
            || ($rule === 'set_quantifier' && $parent?->name === 'simple_select'));
    }

}
