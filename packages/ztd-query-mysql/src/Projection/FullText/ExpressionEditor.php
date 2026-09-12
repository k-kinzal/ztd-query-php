<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Projection\FullText;

use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Expression Editor.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class ExpressionEditor
{
    /**
     * @return array{start: int, end: int, replacement: string}|null
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
     * Query Expression for the supplied MySQL input.
     */
    public function queryExpression(string $queryBody): string
    {
        $previous = null;
        foreach (SqlTokenStream::tokenize($queryBody, MySqlLexerProfile::create())->significantTokens() as $token) {
            if (!$token->isTopLevel()) {
                continue;
            }
            if ($previous !== null && $previous->isKeyword('IN') && ($token->isKeyword('NATURAL') || $token->isKeyword('BOOLEAN'))) {
                return trim(substr($queryBody, 0, $previous->offset));
            }
            if ($previous !== null && $previous->isKeyword('WITH') && $token->isKeyword('QUERY')) {
                return trim(substr($queryBody, 0, $previous->offset));
            }
            $previous = $token;
        }

        return trim($queryBody);
    }
}
