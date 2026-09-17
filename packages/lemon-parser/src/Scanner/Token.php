<?php

declare(strict_types=1);

namespace LemonParser\Scanner;

use LemonParser\Ast\Location;

/**
 * One token of a grammar file.
 *
 * The text is what the token stands for: a string or code without its
 * delimiters, a compound token without its `|` or `/`. The raw spelling is
 * what was written, for error messages.
 *
 * @visibility root
 */
final class Token
{
    /**
     * @param TokenKind $kind What the token is
     * @param string $text What it stands for
     * @param Location $location Where it begins
     * @param string $raw What was written
     */
    public function __construct(
        public readonly TokenKind $kind,
        public readonly string $text,
        public readonly Location $location,
        public readonly string $raw,
    ) {
    }

    /**
     * Reports whether the token is of a given kind.
     *
     * @param TokenKind $kind Kind to compare with
     *
     * @return bool True when the kinds match
     */
    public function is(TokenKind $kind): bool
    {
        return $this->kind === $kind;
    }

    /**
     * Reports whether the token is a given punctuation character.
     *
     * @param string $character The character
     *
     * @return bool True for that character
     */
    public function isPunctuation(string $character): bool
    {
        return $this->kind === TokenKind::Punctuation && $this->text === $character;
    }

    /**
     * Reports whether the token is a word starting with an upper-case letter.
     *
     * @return bool True for a terminal name
     */
    public function isUpperWord(): bool
    {
        return $this->kind === TokenKind::Word && ctype_upper($this->text[0] ?? '');
    }

    /**
     * Reports whether the token is a word starting with a lower-case letter.
     *
     * @return bool True for a nonterminal name
     */
    public function isLowerWord(): bool
    {
        return $this->kind === TokenKind::Word && ctype_lower($this->text[0] ?? '');
    }

    /**
     * Reports whether the token is a word starting with a letter.
     *
     * @return bool True for a symbol name
     */
    public function isAlphaWord(): bool
    {
        return $this->kind === TokenKind::Word && ctype_alpha($this->text[0] ?? '');
    }
}
