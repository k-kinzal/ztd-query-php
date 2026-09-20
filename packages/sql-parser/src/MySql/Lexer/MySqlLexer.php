<?php

declare(strict_types=1);

namespace SqlParser\MySql\Lexer;

use SqlParser\Lexer\Cursor;
use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;
use SqlParser\MySql\MySqlVersion;
use SqlParser\MySql\SqlMode;

/**
 * Reads MySQL text into the terminals of its grammar, as `sql_lex.cc` does.
 *
 * The lexer is a port of MySQL's own state machine: the state a token leaves
 * behind decides how the next one is read, `WITH ROLLUP` is one token, and
 * the input ends with an explicit end-of-input terminal.
 *
 * @visibility root
 */
final class MySqlLexer
{
    /**
     * @param KeywordTable $keywords Keywords of the release
     * @param MySqlVersion $version Release the text is read for
     * @param SqlMode $mode Mode the text is read under
     * @param TriviaScanner $trivia Skips whitespace and comments
     * @param QuotedScanner $quoted Reads quoted tokens
     * @param NumberScanner $numbers Reads numbers
     * @param WordScanner $words Reads words
     * @param OperatorScanner $operators Reads operators
     * @param VariableScanner $variables Reads what follows an at sign
     */
    public function __construct(
        private readonly KeywordTable $keywords,
        private readonly MySqlVersion $version,
        private readonly SqlMode $mode = new SqlMode(),
        private readonly TriviaScanner $trivia = new TriviaScanner(),
        private readonly QuotedScanner $quoted = new QuotedScanner(),
        private readonly NumberScanner $numbers = new NumberScanner(),
        private readonly WordScanner $words = new WordScanner(),
        private readonly OperatorScanner $operators = new OperatorScanner(),
        private readonly VariableScanner $variables = new VariableScanner(),
    ) {
    }

    /**
     * Reads SQL text into lexemes, the end-of-input terminal last.
     *
     * @param string $sql The SQL text
     *
     * @return list<Lexeme> The lexemes in text order
     *
     * @throws LexicalException When the text holds something no token starts with
     */
    public function scan(string $sql): array
    {
        $scan = new Scan(new Cursor($sql), $this->keywords, $this->mode, $this->version);
        $lexemes = [];
        while (true) {
            if ($scan->next === LexerState::Start) {
                $this->trivia->skip($scan);
                if ($scan->cursor->eof()) {
                    break;
                }
            }
            $lexemes[] = $this->next($scan);
        }
        $lexemes[] = new Lexeme('END_OF_INPUT', '', strlen($sql));

        return $this->merged($lexemes, $sql);
    }

    /**
     * Reads one lexeme in the state the previous one left behind.
     *
     * @param Scan $scan The tokenization in progress
     *
     * @return Lexeme The lexeme
     *
     * @throws LexicalException When the text holds something no token starts with
     */
    public function next(Scan $scan): Lexeme
    {
        $state = $scan->next;
        $scan->next = LexerState::Start;
        $start = $scan->cursor->offset();

        return match ($state) {
            LexerState::Hostname => $this->variables->hostname($scan),
            LexerState::SystemVariable => $this->variables->systemVariable($scan),
            LexerState::IdentifierOrKeyword => $this->variables->identifierOrKeyword($scan, $this->words),
            LexerState::IdentifierSeparator => $this->operators->separator($scan),
            LexerState::IdentifierStart => $this->words->identifier($scan, $start),
            LexerState::Start => $scan->cursor->peek() === '@'
                ? $this->variables->at($scan)
                : $this->quoted->scan($scan)
                    ?? $this->numbers->scan($scan, $this->words)
                    ?? $this->words->scan($scan)
                    ?? $this->operators->scan($scan),
        };
    }

    /**
     * Joins `WITH ROLLUP`, and `WITH CUBE` where the release has it, into one lexeme.
     *
     * @param list<Lexeme> $lexemes Lexemes as read
     * @param string $sql The SQL text the joined lexeme spans
     *
     * @return list<Lexeme> Lexemes with the pairs joined
     */
    public function merged(array $lexemes, string $sql): array
    {
        $merged = [];
        for ($index = 0, $count = count($lexemes); $index < $count; $index++) {
            $lexeme = $lexemes[$index];
            $following = $lexemes[$index + 1] ?? null;
            if ($lexeme->name === 'WITH' && $following !== null) {
                $joined = match (true) {
                    $following->name === 'ROLLUP_SYM' => 'WITH_ROLLUP_SYM',
                    $following->name === 'CUBE_SYM' && $this->version->mergesWithCube() => 'WITH_CUBE_SYM',
                    default => null,
                };
                if ($joined !== null) {
                    $merged[] = new Lexeme($joined, substr($sql, $lexeme->offset, $following->end() - $lexeme->offset), $lexeme->offset);
                    $index++;
                    continue;
                }
            }
            $merged[] = $lexeme;
        }

        return $merged;
    }
}
