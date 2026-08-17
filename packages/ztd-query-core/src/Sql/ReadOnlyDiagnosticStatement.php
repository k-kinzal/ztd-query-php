<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

final class ReadOnlyDiagnosticStatement
{
    public static function isSafe(string $sql): bool
    {
        $stream = SqlTokenStream::tokenize($sql);
        if (count($stream->splitStatements()) !== 1) {
            return false;
        }

        $tokens = $stream->significantTokens();
        $first = $tokens[0] ?? null;
        if ($first === null || !$first->isTopLevel()) {
            return false;
        }
        if ($first->isKeyword('SHOW') || $first->isKeyword('DESCRIBE') || $first->isKeyword('DESC')) {
            return true;
        }
        if (!$first->isKeyword('EXPLAIN')) {
            return false;
        }

        $analyzing = false;
        foreach (array_slice($tokens, 1) as $token) {
            if (!$token->isTopLevel() || $token->kind !== SqlTokenKind::Word) {
                continue;
            }
            $keyword = strtoupper($token->text);
            if ($keyword === 'ANALYZE' || $keyword === 'ANALYSE') {
                $analyzing = true;
                continue;
            }
            if ($keyword === 'SELECT') {
                return true;
            }
            if (in_array($keyword, [
                'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'MERGE', 'CREATE', 'ALTER', 'DROP',
                'TRUNCATE', 'COPY', 'LOAD', 'CALL', 'DO', 'EXECUTE',
            ], true)) {
                return false;
            }
        }

        return !$analyzing && count($tokens) > 1;
    }
}
