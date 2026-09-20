<?php

declare(strict_types=1);

namespace SqlParser\Parser;

use RuntimeException;
use SqlParser\Lexer\Token;

/**
 * Raised when a tree cannot be written as text that reads back as that tree.
 *
 * A tree built by a rewrite may hold two tokens the dialect has no way of
 * writing next to each other, whatever stands between them. That is a fault
 * in the tree rather than in the writing of it, so it names the two tokens
 * that could not be told apart.
 *
 * @visibility public
 *
 * @example Reading which two tokens could not be written apart
 *     $left = new \SqlParser\Lexer\Token(1, 'IDENT', 'a', 0);
 *     $right = new \SqlParser\Lexer\Token(2, 'IDENT', 'b', 2);
 *     \SqlParser\Parser\RenderException::unseparable($left, $right)->getMessage() // => 'Cannot write IDENT and IDENT next to each other'
 * @example Reading which token was left unsettled
 *     $last = new \SqlParser\Lexer\Token(1, 'IDENT', 'a', 0);
 *     \SqlParser\Parser\RenderException::unreadable($last)->getMessage() // => 'Text written from the tree does not read back as it, ending at IDENT'
 */
final class RenderException extends RuntimeException
{
    /**
     * @param string $message What went wrong
     * @param Token $left The token on the left
     * @param Token $right The token on the right
     */
    public function __construct(string $message, public readonly Token $left, public readonly Token $right)
    {
        parent::__construct($message);
    }

    /**
     * Names two tokens that cannot be written next to each other.
     *
     * @param Token $left The token on the left
     * @param Token $right The token on the right
     *
     * @return self The exception
     */
    public static function unseparable(Token $left, Token $right): self
    {
        return new self('Cannot write ' . $left->name . ' and ' . $right->name . ' next to each other', $left, $right);
    }

    /**
     * Names the token a text was left not reading back at.
     *
     * @param Token $last The last token written
     *
     * @return self The exception
     */
    public static function unreadable(Token $last): self
    {
        return new self('Text written from the tree does not read back as it, ending at ' . $last->name, $last, $last);
    }
}
