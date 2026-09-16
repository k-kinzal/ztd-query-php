<?php

declare(strict_types=1);

namespace SqlParser\Sqlite\Lexer;

use SqlParser\Lexer\Cursor;
use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;

/**
 * Reads SQLite text into the terminals of its grammar, as `tokenize.c` does.
 *
 * SQLite hands the parser a semicolon at the end of the text when the last
 * token was not one, so every input ends with a statement terminator.
 *
 * @visibility root
 */
final class SqliteLexer
{
    /**
     * @param KeywordTable $keywords Keywords of the release
     * @param array<string, true> $fallbacks Keywords the grammar lets fall back to identifiers
     * @param TriviaScanner $trivia Skips whitespace and comments
     * @param QuotedScanner $quoted Reads quoted tokens
     * @param NumberScanner $numbers Reads numbers
     * @param VariableScanner $variables Reads parameters
     * @param WordScanner $words Reads words
     * @param OperatorScanner $operators Reads operators
     */
    public function __construct(
        private readonly KeywordTable $keywords,
        private readonly array $fallbacks = [],
        private readonly TriviaScanner $trivia = new TriviaScanner(),
        private readonly QuotedScanner $quoted = new QuotedScanner(),
        private readonly NumberScanner $numbers = new NumberScanner(),
        private readonly VariableScanner $variables = new VariableScanner(),
        private readonly WordScanner $words = new WordScanner(),
        private readonly OperatorScanner $operators = new OperatorScanner(),
    ) {
    }

    /**
     * Reads SQL text into lexemes, a statement terminator last.
     *
     * @param string $sql The SQL text
     *
     * @return list<Lexeme> The lexemes in text order
     *
     * @throws LexicalException When the text holds something no token starts with
     */
    public function scan(string $sql): array
    {
        $scan = new Scan(new Cursor($sql), $this->keywords, $this->fallbacks);
        while (true) {
            $this->trivia->skip($scan);
            if ($scan->cursor->eof()) {
                break;
            }
            $scan->lexemes[] = $this->quoted->scan($scan)
                ?? $this->numbers->scan($scan)
                ?? $this->variables->scan($scan)
                ?? $this->words->scan($scan, $this->trivia)
                ?? $this->operators->scan($scan);
        }
        if ($scan->last()?->name !== 'SEMI') {
            $scan->lexemes[] = new Lexeme('SEMI', '', strlen($sql));
        }

        return $scan->lexemes;
    }
}
