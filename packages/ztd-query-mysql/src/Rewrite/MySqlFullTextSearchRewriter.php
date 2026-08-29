<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Rewrite;

use ZtdQuery\Platform\MySql\Dialect\MySqlLexerProfile;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Replaces index-bound MATCH ... AGAINST expressions with CTE-safe relevance expressions.
 */
final class MySqlFullTextSearchRewriter
{
    /**
     * Rewrite.
     *
     * @param string $sql
     * @return string
     */
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
     * Answers the edit that replaces one MATCH ... AGAINST with what it scores.
     *
     * There is no full-text index in the shadow, so the score is worked out
     * from the columns themselves: the words searched for are looked for in
     * everything the MATCH names, run together. A search mode changes how
     * MySQL scores, not what it searches, so it is dropped.
     *
     * @param string $sql Statement being rewritten
     * @param SqlTokenStream $stream The statement, as tokens
     * @param SqlToken $match The MATCH the edit is for
     *
     * @return array{start: int, end: int, replacement: string}|null The edit, or null where this is not a whole MATCH ... AGAINST
     */
    public function expressionEdit(string $sql, SqlTokenStream $stream, SqlToken $match): ?array
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

    /**
     * Answers the words a MATCH searches for, without how it was told to score them.
     *
     * @param string $queryBody Everything AGAINST was given, as written
     *
     * @return string The expression that answers what is searched for
     */
    public function queryExpression(string $queryBody): string
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

    /**
     * Reports whether a token opens a search mode rather than a search term.
     *
     * @param SqlToken $token Token to test
     *
     * @return bool True for the word that opens one of MySQL's search modes
     */
    public static function isSearchMode(SqlToken $token): bool
    {
        return $token->isKeyword('NATURAL') || $token->isKeyword('BOOLEAN');
    }

}
