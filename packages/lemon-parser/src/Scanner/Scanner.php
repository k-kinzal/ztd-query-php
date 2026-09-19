<?php

declare(strict_types=1);

namespace LemonParser\Scanner;

use LemonParser\SyntaxException;

/**
 * Splits a grammar file into tokens as Lemon's `Parse` does.
 *
 * Whitespace and comments are skipped. A token is a string in double
 * quotes, a braced code block, a word of letters, digits and underscores
 * starting with a letter or digit, the arrow `::=`, a compound token `|X`
 * or `/X`, or any single other character.
 *
 * @visibility root
 */
final class Scanner
{
    /**
     * @param CodeReader $code Reads braced code
     */
    public function __construct(private readonly CodeReader $code = new CodeReader())
    {
    }

    /**
     * Scans the whole text.
     *
     * @param string $source The preprocessed grammar file
     *
     * @return non-empty-list<Token> The tokens, ending with an end-of-file token
     */
    public function scan(string $source): array
    {
        $cursor = new Cursor($source);
        $tokens = [];
        while (true) {
            $this->skipTrivia($cursor);
            if ($cursor->eof()) {
                $tokens[] = new Token(TokenKind::End, '', $cursor->location(), '');

                return $tokens;
            }
            $tokens[] = $this->next($cursor);
        }
    }

    /**
     * Moves past whitespace and comments.
     *
     * @param Cursor $cursor The position
     */
    public function skipTrivia(Cursor $cursor): void
    {
        while (!$cursor->eof()) {
            if ($cursor->match('[ \t\n\v\f\r]+') !== null || $cursor->match('//[^\n]*') !== null) {
                continue;
            }
            if (!$cursor->startsWith('/*')) {
                return;
            }
            $cursor->take($cursor->peek(2) === '/' ? 3 : 2);
            if ($cursor->takeUntil('*/') === null) {
                $cursor->take(strlen($cursor->source));
            }
        }
    }

    /**
     * Reads the token the cursor is at.
     *
     * @param Cursor $cursor Positioned at the first byte of a token
     *
     * @return Token The token
     *
     * @throws SyntaxException When a string or code block is not closed
     */
    public function next(Cursor $cursor): Token
    {
        $location = $cursor->location();
        $byte = $cursor->peek();
        if ($byte === '"') {
            $cursor->take(1);
            $text = $cursor->takeUntil('"');
            if ($text === null) {
                throw new SyntaxException('String starting on this line is not terminated before the end of the file.', $location);
            }

            return new Token(TokenKind::String, $text, $location, '"' . $text . '"');
        }
        if ($byte === '{') {
            $text = $this->code->read($cursor);

            return new Token(TokenKind::Code, $text, $location, '{' . $text . '}');
        }
        $word = $cursor->match('[A-Za-z0-9][A-Za-z0-9_]*');
        if ($word !== null) {
            return new Token(TokenKind::Word, $word, $location, $word);
        }
        if ($cursor->match('::=') !== null) {
            return new Token(TokenKind::Arrow, '::=', $location, '::=');
        }
        $compound = $cursor->match('[|/][A-Za-z][A-Za-z0-9_]*');
        if ($compound !== null) {
            return new Token(TokenKind::Compound, substr($compound, 1), $location, $compound);
        }
        $character = $cursor->take(1);

        return new Token(TokenKind::Punctuation, $character, $location, $character);
    }
}
