<?php

declare(strict_types=1);

namespace SqlParser\Lexer;

use SqlParser\Grammar\SymbolTable;

/**
 * Numbers lexemes against the terminals of a grammar.
 *
 * Lexemes name what a scanner recognised and leave out what it skipped. The
 * text between one lexeme and the next is given to the token that follows it,
 * and the text after the last lexeme to the end marker, so the tokens of a
 * text hold every byte of it.
 *
 * @visibility root
 */
final class TerminalIndex
{
    /**
     * @param SymbolTable $symbols Symbols of the grammar the tokens are for
     */
    public function __construct(private readonly SymbolTable $symbols)
    {
    }

    /**
     * Turns lexemes into tokens and closes the stream with the end marker.
     *
     * @param list<Lexeme> $lexemes Lexemes in text order
     * @param string $source The SQL text, for error positions
     *
     * @return list<Token> The tokens, the end marker last
     *
     * @throws LexicalException When a lexeme names a terminal the grammar lacks
     */
    public function tokens(array $lexemes, string $source): array
    {
        $tokens = [];
        $skipped = 0;
        foreach ($lexemes as $lexeme) {
            $tokens[] = $this->token($lexeme, $source, substr($source, $skipped, $lexeme->offset - $skipped));
            $skipped = $lexeme->end();
        }
        $tokens[] = new Token(0, SymbolTable::END, '', strlen($source), substr($source, $skipped));

        return $tokens;
    }

    /**
     * Turns one lexeme into a token.
     *
     * @param Lexeme $lexeme Lexeme to number
     * @param string $source The SQL text, for error positions
     * @param string $leading Whitespace and comments written before the lexeme
     *
     * @return Token The token
     *
     * @throws LexicalException When the lexeme names a terminal the grammar lacks
     */
    public function token(Lexeme $lexeme, string $source, string $leading = ''): Token
    {
        $id = $this->symbols->id($lexeme->name);
        if ($id === null || !$this->symbols->isTerminal($id)) {
            throw LexicalException::unknownTerminal($lexeme->name, $source, $lexeme->offset);
        }

        return new Token($id, $lexeme->name, $lexeme->text, $lexeme->offset, $leading);
    }
}
