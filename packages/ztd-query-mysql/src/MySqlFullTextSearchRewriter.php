<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Replaces index-bound MATCH ... AGAINST expressions with CTE-safe relevance expressions.
 */
final class MySqlFullTextSearchRewriter
{
    public function rewrite(string $sql): string
    {
        $stream = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create());
        /** @var list<array{start: int, end: int, replacement: string}> $edits */
        $edits = [];

        foreach ($stream->significantTokens() as $token) {
            if (!$token->isKeyword('MATCH')) {
                continue;
            }
            $edit = $this->expressionEdit($sql, $stream, $token);
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
     * @return array{start: int, end: int, replacement: string}|null
     */
    private function expressionEdit(string $sql, SqlTokenStream $stream, SqlToken $match): ?array
    {
        $columnsOpen = $stream->significantTokenAfter($match);
        if ($columnsOpen === null) {
            return null;
        }
        $columnsClose = $stream->matchingClosingNestingToken($columnsOpen);
        if ($columnsClose === null) {
            return null;
        }
        $against = $stream->significantTokenAfter($columnsClose);
        if ($against === null || !$against->isKeyword('AGAINST')) {
            return null;
        }
        $queryOpen = $stream->significantTokenAfter($against);
        if ($queryOpen === null) {
            return null;
        }
        $queryClose = $stream->matchingClosingNestingToken($queryOpen);
        if ($queryClose === null) {
            return null;
        }
        $columnsSql = substr($sql, $columnsOpen->endOffset(), $columnsClose->offset - $columnsOpen->endOffset());
        $columns = SqlTokenStream::tokenize($columnsSql, MySqlLexerProfile::create())->splitTopLevel();
        if ($columns === []) {
            return null;
        }

        $queryBody = substr($sql, $queryOpen->endOffset(), $queryClose->offset - $queryOpen->endOffset());
        $querySql = $this->queryExpression($queryBody);
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

    private function queryExpression(string $queryBody): string
    {
        $previous = null;
        foreach (SqlTokenStream::tokenize($queryBody, MySqlLexerProfile::create())->significantTokens() as $token) {
            if (!$token->isTopLevel()) {
                continue;
            }
            if ($previous !== null && $previous->isKeyword('IN') && self::isSearchMode($token)) {
                return trim(substr($queryBody, 0, $previous->offset));
            }
            if ($previous !== null && $previous->isKeyword('WITH') && $token->isKeyword('QUERY')) {
                return trim(substr($queryBody, 0, $previous->offset));
            }
            $previous = $token;
        }

        return trim($queryBody);
    }

    private static function isSearchMode(SqlToken $token): bool
    {
        return $token->isKeyword('NATURAL') || $token->isKeyword('BOOLEAN');
    }

}
