<?php

declare(strict_types=1);

namespace SqlParser\Sqlite\Lexer;

use SqlParser\Lexer\Cursor;
use SqlParser\Lexer\Lexeme;

/**
 * Reads SQLite's identifiers and keywords, deciding the three keywords that depend on context.
 *
 * `WINDOW`, `OVER` and `FILTER` cannot fall back to identifiers in the
 * grammar, so the tokenizer decides for them by looking around: `WINDOW`
 * is a keyword before a name and `AS`, `OVER` after a closing parenthesis
 * and before a parenthesis or a name, `FILTER` after a closing parenthesis
 * and before an opening one.
 *
 * @visibility root
 */
final class WordScanner
{
    /**
     * Reads the word at the cursor, if one starts there.
     *
     * @param Scan $scan The tokenization in progress
     * @param TriviaScanner $trivia Skips what lies between the word and the tokens looked at
     *
     * @return Lexeme|null The lexeme, or null when no word starts here
     */
    public function scan(Scan $scan, TriviaScanner $trivia): ?Lexeme
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        $word = $cursor->match('[A-Za-z_\x80-\xFF][A-Za-z0-9_$\x80-\xFF]*');
        if ($word === null) {
            return null;
        }
        $name = $scan->keywords->lookup($word) ?? 'ID';
        if ($name === 'WINDOW' || $name === 'OVER' || $name === 'FILTER') {
            $name = $this->contextual($scan, $name, $trivia);
        }

        return $scan->lexeme($name, $start);
    }

    /**
     * Decides whether a context-dependent keyword is a keyword here.
     *
     * @param Scan $scan The tokenization in progress, positioned after the word
     * @param string $name The keyword read
     * @param TriviaScanner $trivia Skips what lies between the word and the tokens looked at
     *
     * @return string The keyword, or ID
     */
    public function contextual(Scan $scan, string $name, TriviaScanner $trivia): string
    {
        $previous = $scan->last()?->name;
        $next = $this->ahead($scan, $trivia, 2);
        if ($name === 'WINDOW') {
            return $next[0] === 'ID' && $next[1] === 'AS' ? 'WINDOW' : 'ID';
        }
        if ($previous !== 'RP') {
            return 'ID';
        }
        if ($name === 'OVER') {
            return $next[0] === 'LP' || $next[0] === 'ID' ? 'OVER' : 'ID';
        }

        return $next[0] === 'LP' ? 'FILTER' : 'ID';
    }

    /**
     * Names the next tokens without consuming them, identifiers of every spelling as ID.
     *
     * @param Scan $scan The tokenization in progress
     * @param TriviaScanner $trivia Skips what lies between tokens
     * @param int $count How many tokens to look at
     *
     * @return list<string|null> Terminal names, null past the end
     */
    public function ahead(Scan $scan, TriviaScanner $trivia, int $count): array
    {
        $probe = new Scan(new Cursor($scan->cursor->source), $scan->keywords, $scan->fallbacks);
        $probe->cursor->seek($scan->cursor->offset());
        $names = [];
        for ($index = 0; $index < $count; $index++) {
            $trivia->skip($probe);
            $names[] = $probe->cursor->eof() ? null : $this->probe($probe);
        }

        return $names;
    }

    /**
     * Reads one token of a look-ahead probe, as SQLite's own probe classifies it.
     *
     * Anything that may serve as an identifier, a string included, counts as
     * ID; every other token keeps its own name.
     *
     * @param Scan $probe A scan positioned on the token
     *
     * @return string Terminal name
     */
    public function probe(Scan $probe): string
    {
        $cursor = $probe->cursor;
        $character = $cursor->peek();
        if (($character === 'x' || $character === 'X') && $cursor->peek(1) === "'") {
            $cursor->take(1);
            $this->skipQuoted($cursor, "'");

            return 'BLOB';
        }
        $word = $cursor->match('[A-Za-z_\x80-\xFF][A-Za-z0-9_$\x80-\xFF]*');
        if ($word !== null) {
            $name = $probe->keywords->lookup($word) ?? 'ID';

            return in_array($name, ['ID', 'JOIN_KW', 'WINDOW', 'OVER'], true) || isset($probe->fallbacks[$name]) ? 'ID' : $name;
        }
        if ($character === "'" || $character === '"' || $character === '`') {
            $this->skipQuoted($cursor, $character);

            return 'ID';
        }
        if ($character === '[') {
            $cursor->skipPastOrEnd(']');

            return 'ID';
        }
        if ($cursor->match('[0-9.][0-9A-Za-z_.$\x80-\xFF]*|[?:@$#][A-Za-z0-9_$\x80-\xFF:]*') !== null) {
            return 'LITERAL';
        }
        foreach (OperatorScanner::OPERATORS as $spelling => $name) {
            if ($cursor->startsWith($spelling)) {
                $cursor->take(strlen($spelling));

                return $name;
            }
        }
        $cursor->take(1);

        return 'ILLEGAL';
    }

    /**
     * Skips a quoted run, or the rest of the text when it never closes.
     *
     * @param Cursor $cursor Reads the SQL text, positioned on the opening quote
     * @param string $quote The quote character
     */
    public function skipQuoted(Cursor $cursor, string $quote): void
    {
        if ($cursor->takeQuoted($quote) === null) {
            $cursor->seek(strlen($cursor->source));
        }
    }
}
