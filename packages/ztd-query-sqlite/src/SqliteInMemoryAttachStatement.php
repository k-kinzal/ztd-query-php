<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite;

use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

final class SqliteInMemoryAttachStatement
{
    public static function isSafe(string $sql): bool
    {
        $stream = SqlTokenStream::tokenize($sql);
        if (count($stream->splitStatements()) !== 1) {
            return false;
        }

        $tokens = $stream->significantTokens();
        $last = $tokens[count($tokens) - 1] ?? null;
        if ($last !== null && self::isSymbol($last, ';')) {
            array_pop($tokens);
        }

        $index = 0;
        if (($tokens[$index] ?? null)?->isKeyword('ATTACH') !== true) {
            return false;
        }
        $index++;
        if (($tokens[$index] ?? null)?->isKeyword('DATABASE') === true) {
            $index++;
        }

        $path = $tokens[$index] ?? null;
        if ($path === null || $path->kind !== SqlTokenKind::String || self::stringValue($path) !== ':memory:') {
            return false;
        }
        $index++;
        if (($tokens[$index] ?? null)?->isKeyword('AS') !== true) {
            return false;
        }
        $index++;

        $aliasEnd = self::identifierEndIndex($tokens, $index);

        return $aliasEnd !== null && $aliasEnd === count($tokens);
    }

    private static function stringValue(SqlToken $token): string
    {
        $inner = substr($token->text, 1, -1);

        return str_replace("''", "'", $inner);
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
        if (!self::isSymbol($token, '[')) {
            return null;
        }

        $name = $tokens[$index + 1] ?? null;
        $end = $tokens[$index + 2] ?? null;
        if ($name?->kind !== SqlTokenKind::Word || $end === null || !self::isSymbol($end, ']')) {
            return null;
        }

        return $index + 3;
    }

    private static function isSymbol(SqlToken $token, string $symbol): bool
    {
        return $token->kind === SqlTokenKind::Symbol && $token->text === $symbol;
    }
}
