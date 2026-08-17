<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite;

use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

final class SqliteIndexHintStripper
{
    /**
     * @param list<string> $shadowTables
     */
    public function strip(string $sql, array $shadowTables): string
    {
        $targets = [];
        foreach ($shadowTables as $table) {
            $targets[strtolower($table)] = true;
        }
        if ($targets === []) {
            return $sql;
        }

        $stream = SqlTokenStream::tokenize($sql);
        $tokens = $stream->significantTokens();
        /** @var list<array{start: int, end: int}> $removals */
        $removals = [];

        foreach ((new SqliteSelectRelationParser())->references($sql) as $reference) {
            if (!isset($targets[strtolower($reference['name'])])) {
                continue;
            }
            $index = self::tokenIndexAtOrAfter($tokens, $reference['end']);
            $index = self::skipAlias($tokens, $index);
            $indexed = $tokens[$index] ?? null;
            $next = $tokens[$index + 1] ?? null;

            if ($indexed?->isKeyword('NOT') === true && $next?->isKeyword('INDEXED') === true) {
                $removals[] = ['start' => $indexed->offset, 'end' => $next->endOffset()];
                continue;
            }
            if ($indexed?->isKeyword('INDEXED') !== true || $next?->isKeyword('BY') !== true) {
                continue;
            }
            $nameEnd = self::identifierEndIndex($tokens, $index + 2);
            if ($nameEnd === null) {
                continue;
            }
            $removals[] = [
                'start' => $indexed->offset,
                'end' => $tokens[$nameEnd - 1]->endOffset(),
            ];
        }

        usort($removals, static fn (array $left, array $right): int => $right['start'] <=> $left['start']);
        foreach ($removals as $removal) {
            $sql = substr_replace($sql, '', $removal['start'], $removal['end'] - $removal['start']);
        }

        return $sql;
    }

    /**
     * @param list<SqlToken> $tokens
     */
    private static function tokenIndexAtOrAfter(array $tokens, int $offset): int
    {
        foreach ($tokens as $index => $token) {
            if ($token->offset >= $offset) {
                return $index;
            }
        }

        return count($tokens);
    }

    /**
     * @param list<SqlToken> $tokens
     */
    private static function skipAlias(array $tokens, int $index): int
    {
        if (($tokens[$index] ?? null)?->isKeyword('AS') === true) {
            return self::identifierEndIndex($tokens, $index + 1) ?? $index;
        }

        $candidate = $tokens[$index] ?? null;
        if ($candidate === null || self::isSourceBoundary($candidate)) {
            return $index;
        }

        return self::identifierEndIndex($tokens, $index) ?? $index;
    }

    private static function isSourceBoundary(SqlToken $token): bool
    {
        if ($token->kind === SqlTokenKind::Symbol) {
            return true;
        }

        foreach ([
            'INDEXED', 'NOT', 'WHERE', 'GROUP', 'HAVING', 'ORDER', 'LIMIT', 'OFFSET',
            'JOIN', 'LEFT', 'RIGHT', 'FULL', 'INNER', 'CROSS', 'NATURAL', 'ON', 'USING',
            'UNION', 'INTERSECT', 'EXCEPT', 'RETURNING', 'WINDOW', 'FOR',
        ] as $keyword) {
            if ($token->isKeyword($keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<SqlToken> $tokens
     */
    private static function identifierEndIndex(array $tokens, int $index): ?int
    {
        $token = $tokens[$index] ?? null;
        if ($token === null) {
            return null;
        }
        if ($token->kind === SqlTokenKind::Word || $token->kind === SqlTokenKind::QuotedIdentifier) {
            return $index + 1;
        }
        if ($token->kind !== SqlTokenKind::Symbol || $token->text !== '[') {
            return null;
        }

        foreach ($tokens as $endIndex => $endToken) {
            if ($endIndex > $index && $endToken->kind === SqlTokenKind::Symbol && $endToken->text === ']') {
                return $endIndex + 1;
            }
        }

        return null;
    }
}
