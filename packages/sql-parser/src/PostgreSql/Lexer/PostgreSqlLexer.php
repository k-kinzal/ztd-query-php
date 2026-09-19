<?php

declare(strict_types=1);

namespace SqlParser\PostgreSql\Lexer;

use SqlParser\Lexer\Cursor;
use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;

/**
 * Reads PostgreSQL text into the terminals of its grammar, as `scan.l` and `parser.c` do.
 *
 * @visibility root
 */
final class PostgreSqlLexer
{
    /**
     * @param KeywordTable $keywords Keywords of the release
     * @param TriviaScanner $trivia Skips whitespace and comments
     * @param QuotedScanner $quoted Reads quoted tokens
     * @param NumberScanner $numbers Reads numbers and parameters
     * @param WordScanner $words Reads words
     * @param OperatorScanner $operators Reads operators and punctuation
     * @param LookaheadFilter $lookahead Renames tokens by what follows them
     */
    public function __construct(
        private readonly KeywordTable $keywords,
        private readonly TriviaScanner $trivia = new TriviaScanner(),
        private readonly QuotedScanner $quoted = new QuotedScanner(),
        private readonly NumberScanner $numbers = new NumberScanner(),
        private readonly WordScanner $words = new WordScanner(),
        private readonly OperatorScanner $operators = new OperatorScanner(),
        private readonly LookaheadFilter $lookahead = new LookaheadFilter(),
    ) {
    }

    /**
     * Reads SQL text into lexemes.
     *
     * @param string $sql The SQL text
     *
     * @return list<Lexeme> The lexemes in text order
     *
     * @throws LexicalException When the text holds something no token starts with
     */
    public function scan(string $sql): array
    {
        $scan = new Scan(new Cursor($sql), $this->keywords);
        $lexemes = [];
        while (true) {
            $this->trivia->skip($scan);
            if ($scan->cursor->eof()) {
                break;
            }
            $lexemes[] = $this->quoted->scan($scan)
                ?? $this->numbers->scan($scan)
                ?? $this->words->scan($scan)
                ?? $this->operators->scan($scan);
        }

        return $this->lookahead->apply($lexemes, $sql);
    }
}
