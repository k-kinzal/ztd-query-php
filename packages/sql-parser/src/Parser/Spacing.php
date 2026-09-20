<?php

declare(strict_types=1);

namespace SqlParser\Parser;

use SqlParser\Lexer\Token;

/**
 * Says what to write before a token that has nothing written before it.
 *
 * How much space stands between two tokens never matters, because a lexer
 * drops whitespace and comments before the parser sees them. Whether any
 * space stands there at all can matter a great deal: a lexer may read a word
 * held against a dot as a name and the same word standing apart from one as
 * the keyword it spells, may start a variable only where its sigil touches
 * the name, and may read a call only where the name touches its bracket.
 *
 * A token that was read carries the trivia it stood after, which says what
 * it needs better than any rule could. A token a rewrite built carries none,
 * and this is the guess made for it: brackets, commas, semicolons, dots and
 * sigils are held against what they belong to, and everything else is
 * separated. It is only ever a first guess, because the guess is read back
 * before it is kept.
 *
 * @visibility root
 */
final class Spacing
{
    /**
     * Texts a separator never precedes, for tokens with no trivia to go by.
     */
    private const TIGHT_BEFORE = ['(' => true, ')' => true, ',' => true, '.' => true, ';' => true];

    /**
     * Texts a separator never follows, for tokens with no trivia to go by.
     */
    private const TIGHT_AFTER = ['(' => true, '.' => true, '@' => true];

    /**
     * Reports whether a space belongs between two neighbouring tokens.
     *
     * @param Token $left The token on the left
     * @param Token $right The token on the right
     *
     * @return bool True when the two have to be written apart
     */
    public function separates(Token $left, Token $right): bool
    {
        return !isset(self::TIGHT_BEFORE[$right->text]) && !isset(self::TIGHT_AFTER[$left->text]);
    }
}
