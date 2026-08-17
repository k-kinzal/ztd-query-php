<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenDialect;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Replaces index-bound MATCH ... AGAINST expressions with CTE-safe relevance expressions.
 */
final class MySqlFullTextSearchRewriter
{
    public function rewrite(string $sql): string
    {
        $tokens = SqlTokenStream::tokenize($sql, SqlTokenDialect::MySql)->significantTokens();
        /** @var list<array{start: int, end: int, replacement: string}> $edits */
        $edits = [];

        foreach ($tokens as $index => $token) {
            if (!$token->isKeyword('MATCH')) {
                continue;
            }
            $edit = $this->expressionEdit($sql, $tokens, $index);
            if ($edit !== null) {
                $edits[] = $edit;
            }
        }

        usort($edits, static fn (array $left, array $right): int => $right['start'] <=> $left['start']);
        foreach ($edits as $edit) {
            $sql = substr_replace($sql, $edit['replacement'], $edit['start'], $edit['end'] - $edit['start']);
        }

        return $sql;
    }

    /**
     * @param list<SqlToken> $tokens
     * @return array{start: int, end: int, replacement: string}|null
     */
    private function expressionEdit(string $sql, array $tokens, int $matchIndex): ?array
    {
        $match = $tokens[$matchIndex];
        $columnsOpen = $tokens[$matchIndex + 1] ?? null;
        if ($columnsOpen === null || !self::isOpeningParenthesis($columnsOpen)) {
            return null;
        }
        $columnsCloseIndex = self::closingParenthesisIndex($tokens, $matchIndex + 1);
        if ($columnsCloseIndex === null) {
            return null;
        }
        $columnsClose = $tokens[$columnsCloseIndex];
        $against = $tokens[$columnsCloseIndex + 1] ?? null;
        $queryOpen = $tokens[$columnsCloseIndex + 2] ?? null;
        if ($against === null
            || !$against->isKeyword('AGAINST')
            || $queryOpen === null
            || !self::isOpeningParenthesis($queryOpen)
        ) {
            return null;
        }
        $queryOpenIndex = $columnsCloseIndex + 2;
        $queryCloseIndex = self::closingParenthesisIndex($tokens, $queryOpenIndex);
        if ($queryCloseIndex === null) {
            return null;
        }
        $queryClose = $tokens[$queryCloseIndex];
        $columnsSql = substr($sql, $columnsOpen->endOffset(), $columnsClose->offset - $columnsOpen->endOffset());
        $columns = SqlTokenStream::tokenize($columnsSql, SqlTokenDialect::MySql)->splitTopLevel();
        if ($columns === []) {
            return null;
        }

        $queryEnd = $this->queryExpressionEnd($tokens, $queryOpenIndex, $queryCloseIndex);
        $querySql = trim(substr($sql, $queryOpen->endOffset(), $queryEnd - $queryOpen->endOffset()));
        if ($querySql === '') {
            return null;
        }

        $documentParts = array_map(
            static fn (string $column): string => "COALESCE(CAST(($column) AS CHAR), '')",
            $columns,
        );
        $document = "LOWER(CONCAT_WS(' ', " . implode(', ', $documentParts) . '))';
        $needle = "LOWER(NULLIF(TRIM(CAST(($querySql) AS CHAR)), ''))";
        $replacement = "(CASE WHEN LOCATE($needle, $document) > 0 THEN 1.0 ELSE 0.0 END)";

        return [
            'start' => $match->offset,
            'end' => $queryClose->endOffset(),
            'replacement' => $replacement,
        ];
    }

    /** @param list<SqlToken> $tokens */
    private function queryExpressionEnd(array $tokens, int $openIndex, int $closeIndex): int
    {
        $queryOpen = $tokens[$openIndex];
        for ($index = $openIndex + 1; $index < $closeIndex; $index++) {
            $token = $tokens[$index];
            if ($token->depth !== $queryOpen->depth + 1 || $token->bracketDepth !== $queryOpen->bracketDepth) {
                continue;
            }
            $next = $tokens[$index + 1] ?? null;
            if ($token->isKeyword('IN') && $next !== null && self::isSearchMode($next)) {
                return $token->offset;
            }
            if ($token->isKeyword('WITH') && $next !== null && $next->isKeyword('QUERY')) {
                return $token->offset;
            }
        }

        return $tokens[$closeIndex]->offset;
    }

    private static function isSearchMode(SqlToken $token): bool
    {
        return $token->isKeyword('NATURAL') || $token->isKeyword('BOOLEAN');
    }

    private static function isOpeningParenthesis(SqlToken $token): bool
    {
        return $token->kind === SqlTokenKind::Symbol && $token->text === '(';
    }

    /** @param list<SqlToken> $tokens */
    private static function closingParenthesisIndex(array $tokens, int $openIndex): ?int
    {
        $open = $tokens[$openIndex];
        for ($index = $openIndex + 1, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token->kind !== SqlTokenKind::Symbol
                || $token->text !== ')'
                || $token->depth !== $open->depth
                || $token->bracketDepth !== $open->bracketDepth
            ) {
                continue;
            }

            return $index;
        }

        return null;
    }
}
