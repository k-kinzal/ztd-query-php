<?php

declare(strict_types=1);

namespace SqlParser\Lexer;

/**
 * Reads SQL text into the terminals of a grammar.
 *
 * Each dialect reads text its own way, and only its own lexer knows what a
 * given text reads back as. Writing a tree out asks that question often
 * enough that it is worth naming: this is the dialect's answer to it.
 *
 * @visibility root
 */
interface Tokenizer
{
    /**
     * Reads SQL text into the terminals of the grammar, the end marker last.
     *
     * @param string $sql The SQL text
     *
     * @return list<Token> The tokens in text order
     *
     * @throws LexicalException When the text holds something no token starts with
     */
    public function tokenize(string $sql): array;
}
