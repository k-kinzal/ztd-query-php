<?php

declare(strict_types=1);

namespace SqlParser\Parser;

use SqlParser\Lexer\Token;

/**
 * Decides whether two neighbouring tokens have to be written apart.
 *
 * How much space stands between two tokens never matters, because a lexer
 * drops whitespace and comments before the parser sees them. Whether any
 * space stands there at all can matter a great deal: a lexer may read a word
 * held against a dot as a name and the same word standing apart from one as
 * the keyword it spells, may start a variable only where its sigil touches
 * the name, and may read a call only where the name touches its bracket.
 *
 * Two tokens still standing where they were read say for themselves whether
 * they touched, in the offsets they carry. Once either has been built or
 * detached there is nothing to go by, and the punctuation decides: brackets,
 * commas, semicolons, dots and sigils are held against what they belong to,
 * and everything else is separated.
 *
 * @visibility root
 */
final class Spacing
{
    /**
     * Texts a separator never precedes, for tokens with no offset to go by.
     */
    private const TIGHT_BEFORE = ['(' => true, ')' => true, ',' => true, '.' => true, ';' => true];

    /**
     * Texts a separator never follows, for tokens with no offset to go by.
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
        if (!$left->isDetached() && !$right->isDetached()) {
            return $left->end() !== $right->offset;
        }

        return !isset(self::TIGHT_BEFORE[$right->text]) && !isset(self::TIGHT_AFTER[$left->text]);
    }
}
