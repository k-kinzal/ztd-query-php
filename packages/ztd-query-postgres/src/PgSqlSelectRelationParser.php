<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * The pg sql select relation parser.
 */
final class PgSqlSelectRelationParser
{
    /**
     * @return list<string>
     */
    public function fromClauses(string $sql): array
    {
        $tokens = $this->tokens($sql);
        /** @var array<int, array<int, bool>> $selectScopes */
        $selectScopes = [];
        $clauses = [];

        foreach ($tokens as $token) {
            if ($token->isKeyword('SELECT')) {
                $selectScopes[$token->depth][$token->bracketDepth] = true;
                continue;
            }
            if ($token->isKeyword('UNION') || $token->isKeyword('INTERSECT') || $token->isKeyword('EXCEPT')) {
                $selectScopes[$token->depth][$token->bracketDepth] = false;
                continue;
            }
            if (!$token->isKeyword('FROM') || ($selectScopes[$token->depth][$token->bracketDepth] ?? false) !== true) {
                continue;
            }

            $end = $this->findFromEnd($sql, $tokens, $token);
            $clause = trim(substr($sql, $token->endOffset(), $end - $token->endOffset()));
            if ($clause !== '') {
                $clauses[] = $clause;
            }
            $selectScopes[$token->depth][$token->bracketDepth] = false;
        }

        return $clauses;
    }

    /**
     * @return list<string>
     */
    public function tableNames(string $sql): array
    {
        $names = [];
        foreach ($this->references($sql) as $reference) {
            $normalized = strtolower($reference['name']);
            if (!isset($names[$normalized])) {
                $names[$normalized] = $reference['name'];
            }
        }

        return array_values($names);
    }

    /**
     * @return list<array{name: string, start: int, unqualifiedStart: int, end: int}>
     */
    public function references(string $sql): array
    {
        $tokens = $this->tokens($sql);
        /** @var array<int, array<int, bool>> $selectScopes */
        $selectScopes = [];
        $references = [];

        foreach ($tokens as $token) {
            if ($token->isKeyword('SELECT')) {
                $selectScopes[$token->depth][$token->bracketDepth] = true;
                continue;
            }
            if (!$token->isKeyword('FROM') || ($selectScopes[$token->depth][$token->bracketDepth] ?? false) !== true) {
                continue;
            }

            $clauseStart = $token->endOffset();
            $clauseEnd = $this->findFromEnd($sql, $tokens, $token);
            $clause = substr($sql, $clauseStart, $clauseEnd - $clauseStart);
            foreach ($this->referencesFromClause($clause) as $reference) {
                $references[] = [
                    'name' => $reference['name'],
                    'start' => $clauseStart + $reference['start'],
                    'unqualifiedStart' => $clauseStart + $reference['unqualifiedStart'],
                    'end' => $clauseStart + $reference['end'],
                ];
            }
            $selectScopes[$token->depth][$token->bracketDepth] = false;
        }

        return $references;
    }

    /**
     * @param list<string> $relationNames
     */
    public function unqualify(string $sql, array $relationNames): string
    {
        $targets = array_map(strtolower(...), $relationNames);
        $removals = [];

        foreach ($this->references($sql) as $reference) {
            if ($reference['start'] === $reference['unqualifiedStart']
                || !in_array(strtolower($reference['name']), $targets, true)
            ) {
                continue;
            }
            $removals[] = ['start' => $reference['start'], 'end' => $reference['unqualifiedStart']];
        }

        usort($removals, static fn (array $left, array $right): int => $right['start'] <=> $left['start']);
        foreach ($removals as $removal) {
            $sql = substr_replace($sql, '', $removal['start'], $removal['end'] - $removal['start']);
        }

        return $sql;
    }

    /**
     * Answers every table a FROM clause names, and where each is written.
     *
     * A parenthesised join is a join like any other, so what it names is
     * named by the clause around it; a parenthesised query is not, so what it
     * names belongs to itself.
     *
     * @param string $clause The clause
     *
     * @return list<array{name: string, start: int, unqualifiedStart: int, end: int}> What it answers
     */
    public function referencesFromClause(string $clause): array
    {
        $tokens = $this->tokens($clause);
        $references = [];
        $expectSource = true;

        foreach ($tokens as $index => $token) {
            if (!$token->isTopLevel()) {
                continue;
            }
            if ($token->isKeyword('JOIN')) {
                $expectSource = true;
                continue;
            }
            if ($token->kind === SqlTokenKind::Symbol && $token->text === ',') {
                $expectSource = true;
                continue;
            }
            if (!$expectSource) {
                continue;
            }
            if ($token->isKeyword('LATERAL') || $token->isKeyword('ONLY')) {
                continue;
            }

            if ($token->kind === SqlTokenKind::Symbol && $token->text === '(') {
                $closingToken = $this->closingToken($tokens, $index);
                if ($closingToken === null) {
                    continue;
                }
                $innerStart = $token->endOffset();
                $inner = substr($clause, $innerStart, $closingToken->offset - $innerStart);
                $innerTokens = $this->tokens($inner);
                if ($innerTokens === []) {
                    continue;
                }
                if ($innerTokens[0]->isKeyword('SELECT')
                    || $innerTokens[0]->isKeyword('WITH')
                    || $innerTokens[0]->isKeyword('VALUES')
                ) {
                    continue;
                }
                foreach ($this->referencesFromClause($inner) as $reference) {
                    $references[] = [
                        'name' => $reference['name'],
                        'start' => $innerStart + $reference['start'],
                        'unqualifiedStart' => $innerStart + $reference['unqualifiedStart'],
                        'end' => $innerStart + $reference['end'],
                    ];
                }
                continue;
            }

            $expectSource = false;
            $reference = $this->referenceAt($clause, $tokens, $index);
            if ($reference !== null) {
                $references[] = $reference;
            }
        }

        return $references;
    }

    /**
     * Answers the parenthesis that closes the one written here.
     *
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param int $openingIndex The opening index
     *
     * @return SqlToken|null What it answers
     */
    public function closingToken(array $tokens, int $openingIndex): ?SqlToken
    {
        for ($index = $openingIndex; isset($tokens[$index]); $index++) {
            $candidate = $tokens[$index];
            if ($candidate->text === ')' && $candidate->isTopLevel()) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Answers the table named here, and where its name is written.
     *
     * A name followed by a parenthesis is a function call, not a table, and a
     * qualified name is read down to its last part -- but where the qualifier
     * starts is answered too, so a caller can take the qualifier off.
     *
     * @param string $sql Statement being read, as written
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param int $index Where to read
     *
     * @return array{name: string, start: int, unqualifiedStart: int, end: int}|null What it answers
     */
    public function referenceAt(string $sql, array $tokens, int $index): ?array
    {
        $token = $tokens[$index];
        if ($token->isKeyword('VALUES') || $token->isKeyword('SELECT') || $token->isKeyword('WITH')) {
            return null;
        }

        $component = $this->identifierComponentAt($tokens, $index);
        if ($component === null) {
            return null;
        }
        [$name, $nextIndex, $start, $unqualifiedStart, $end] = $component;

        while (($tokens[$nextIndex] ?? null)?->kind === SqlTokenKind::Symbol
            && $tokens[$nextIndex]->text === '.'
        ) {
            $component = $this->identifierComponentAt($tokens, $nextIndex + 1);
            if ($component === null) {
                break;
            }
            [$name, $nextIndex, , $unqualifiedStart, $end] = $component;
        }

        $next = $tokens[$nextIndex] ?? null;
        if ($next !== null && $next->kind === SqlTokenKind::Symbol && $next->text === '(') {
            return null;
        }

        return [
            'name' => $name,
            'start' => $start,
            'unqualifiedStart' => $unqualifiedStart,
            'end' => $end,
        ];
    }

    /**
     * Answers one part of a name, and where it is written.
     *
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param int $index Where to read
     *
     * @return array{string, int, int, int, int}|null What it answers
     */
    public function identifierComponentAt(array $tokens, int $index): ?array
    {
        $token = $tokens[$index] ?? null;
        if ($token === null) {
            return null;
        }
        if ($token->kind === SqlTokenKind::Word) {
            return [$token->text, $index + 1, $token->offset, $token->offset, $token->endOffset()];
        }
        $name = PgSqlLexerProfile::create()->quotedIdentifierValue($token->text);
        if ($name === null) {
            return null;
        }

        return [
            $name,
            $index + 1,
            $token->offset,
            $token->offset,
            $token->endOffset(),
        ];
    }

    /**
     * Answers where a FROM clause ends.
     *
     * It ends at whichever clause opens next at the same depth, or wherever
     * the statement the FROM belongs to ends.
     *
     * @param string $sql Statement being read, as written
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param SqlToken $fromToken The from token
     *
     * @return int What it answers
     */
    public function findFromEnd(string $sql, array $tokens, SqlToken $fromToken): int
    {
        $terminators = [
            ['WHERE'], ['GROUP', 'BY'], ['HAVING'], ['WINDOW'], ['ORDER', 'BY'],
            ['LIMIT'], ['OFFSET'], ['FETCH'], ['FOR'], ['RETURNING'],
            ['UNION'], ['INTERSECT'], ['EXCEPT'],
        ];
        $afterFrom = false;
        foreach ($tokens as $index => $token) {
            if (!$afterFrom) {
                $afterFrom = $token === $fromToken;
                continue;
            }
            if ($token->depth < $fromToken->depth || $token->bracketDepth < $fromToken->bracketDepth) {
                return $token->offset;
            }
            if ($token->depth !== $fromToken->depth || $token->bracketDepth !== $fromToken->bracketDepth) {
                continue;
            }
            foreach ($terminators as $sequence) {
                if ($this->matchesKeywordSequence($tokens, $index, $sequence)) {
                    return $token->offset;
                }
            }
        }

        return strlen($sql);
    }

    /**
     * Reports whether a run of keywords is written starting here.
     *
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param int $index Where to read
     * @param non-empty-list<string> $keywords Keywords to look for, in order
     *
     * @return bool What it answers
     */
    public function matchesKeywordSequence(array $tokens, int $index, array $keywords): bool
    {
        foreach ($keywords as $relative => $keyword) {
            $candidate = $tokens[$index + $relative] ?? null;
            if ($candidate === null || !$candidate->isKeyword($keyword)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reads a statement into the lexemes that carry meaning.
     *
     * @param string $sql Statement being read, as written
     *
     * @return list<SqlToken> What it answers
     */
    public function tokens(string $sql): array
    {
        return SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create())->significantTokens();
    }
}
