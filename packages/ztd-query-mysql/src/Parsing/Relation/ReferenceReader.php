<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Parsing\Relation;

use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Reference Reader.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class ReferenceReader
{
    /**
     * @return list<array{name: string, start: int, unqualifiedStart: int, end: int}>
     */
    public function referencesFromClause(string $clause): array
    {
        $tokens = (SqlTokenStream::tokenize($clause, MySqlLexerProfile::create())->significantTokens());
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
            if ($token->isKeyword('LATERAL')) {
                continue;
            }

            if ($token->kind === SqlTokenKind::Symbol && $token->text === '(') {
                array_push($references, ...$this->parenthesizedReferences($clause, $tokens, $index));
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
     * @param list<SqlToken> $tokens
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
     * @param list<SqlToken> $tokens
     * @return array{name: string, start: int, unqualifiedStart: int, end: int}|null
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
     * @param list<SqlToken> $tokens
     * @return array{string, int, int, int, int}|null
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
        $name = MySqlLexerProfile::create()->quotedIdentifierValue($token->text);
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
     * @param list<SqlToken> $tokens
     */
    public function findFromEnd(string $sql, array $tokens, SqlToken $fromToken): int
    {
        $terminators = [
            ['WHERE'], ['GROUP', 'BY'], ['HAVING'], ['WINDOW'], ['ORDER', 'BY'],
            ['LIMIT'], ['PROCEDURE'], ['INTO'], ['FOR'], ['LOCK'],
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
     * @param list<SqlToken> $tokens
     * @param non-empty-list<string> $keywords
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
     * @param list<SqlToken> $tokens
     * @return list<array{name: string, start: int, unqualifiedStart: int, end: int}>
     */
    public function parenthesizedReferences(string $clause, array $tokens, int $index): array
    {
        $references = [];
        $closingToken = $this->closingToken($tokens, $index);
        if ($closingToken === null) {
            return [];
        }
        $innerStart = $tokens[$index]->endOffset();
        $inner = substr($clause, $innerStart, $closingToken->offset - $innerStart);
        $innerTokens = (SqlTokenStream::tokenize($inner, MySqlLexerProfile::create())->significantTokens());
        if ($innerTokens === []) {
            return [];
        }
        if ($innerTokens[0]->isKeyword('SELECT')
            || $innerTokens[0]->isKeyword('WITH')
            || $innerTokens[0]->isKeyword('VALUES')
        ) {
            return [];
        }
        foreach ($this->referencesFromClause($inner) as $reference) {
            $references[] = [
                'name' => $reference['name'],
                'start' => $innerStart + $reference['start'],
                'unqualifiedStart' => $innerStart + $reference['unqualifiedStart'],
                'end' => $innerStart + $reference['end'],
            ];
        }
        return $references;
    }

}
