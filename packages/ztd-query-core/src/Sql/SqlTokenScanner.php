<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

use ZtdQuery\Sql\Reader\SqlDelimitedReader;
use ZtdQuery\Sql\Reader\SqlParameterReader;
use ZtdQuery\Sql\Reader\SqlTriviaReader;
use ZtdQuery\Sql\Reader\SqlWordReader;

/**
 * Reads a statement into the lexemes it is written as, losing nothing.
 *
 * Every byte of the statement ends up in exactly one token, whitespace and
 * comments included, so that the statement can always be written back out
 * unchanged. Nothing here knows any SQL: which quotes open a string, what
 * begins a comment and what may spell an identifier are all asked of the
 * profile, so one reader serves every dialect.
 *
 * What each kind of lexeme is spelled like is a reader of its own; this one
 * asks each in turn and keeps the depths, because only a statement read as
 * a whole can say how deeply a lexeme is nested.
 */
final class SqlTokenScanner
{
    /**
     * Creates the scanner from the readers it asks.
     *
     * @param SqlTriviaReader $trivia What reads whitespace and comments
     * @param SqlDelimitedReader $delimited What reads strings and quoted identifiers
     * @param SqlParameterReader $parameters What reads placeholders
     * @param SqlWordReader $words What reads bare words and numbers
     */
    public function __construct(
        private readonly SqlTriviaReader $trivia = new SqlTriviaReader(),
        private readonly SqlDelimitedReader $delimited = new SqlDelimitedReader(),
        private readonly SqlParameterReader $parameters = new SqlParameterReader(),
        private readonly SqlWordReader $words = new SqlWordReader(),
    ) {
    }

    /**
     * Reads a statement into its lexemes.
     *
     * @param string $sql The statement, as written
     * @param SqlLexerProfile $profile What the dialect spells things with
     *
     * @return list<SqlToken> Every lexeme, in the order they were written
     */
    public function scan(string $sql, SqlLexerProfile $profile): array
    {
        $tokens = [];
        $length = strlen($sql);
        $offset = 0;
        $depth = 0;
        $bracketDepth = 0;

        while ($offset < $length) {
            $start = $offset;
            $lexeme = $this->trivia->readAt($sql, $offset, $profile)
                ?? $this->delimited->readAt($sql, $offset, $profile)
                ?? $this->parameters->readAt($sql, $offset, $profile)
                ?? $this->words->readAt($sql, $offset, $profile);
            if ($lexeme !== null && $lexeme->end > $offset) {
                $offset = $lexeme->end;
                $tokens[] = SqlToken::slice($sql, $lexeme->kind, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            $char = $sql[$offset];
            if ($profile->isNestingClosing($char)) {
                $depth = max(0, $depth - 1);
            } elseif ($profile->isBracketClosing($char)) {
                $bracketDepth = max(0, $bracketDepth - 1);
            }
            $offset++;
            $tokens[] = SqlToken::slice($sql, SqlTokenKind::Symbol, $start, $offset, $depth, $bracketDepth);
            if ($profile->isNestingOpening($char)) {
                $depth++;
            } elseif ($profile->isBracketOpening($char)) {
                $bracketDepth++;
            }
        }

        return $tokens;
    }
}
