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
        $tokens = SqlTokenStream::tokenize($sql)->significantTokens();
        $last = $tokens[count($tokens) - 1] ?? null;
        if ($last !== null && self::isSymbol($last, ';')) {
            array_pop($tokens);
        }

        if (count($tokens) < 4 || !$tokens[0]->isKeyword('ATTACH')) {
            return false;
        }

        $pathIndex = 1;
        if ($tokens[$pathIndex]->isKeyword('DATABASE')) {
            $pathIndex++;
        }

        $path = $tokens[$pathIndex];
        if ($path->kind !== SqlTokenKind::String || $path->text !== "':memory:'") {
            return false;
        }

        $as = $tokens[$pathIndex + 1];
        if (!$as->isKeyword('AS')) {
            return false;
        }

        return self::isIdentifierSuffix($tokens, $pathIndex + 2);
    }

    /**
     * @param list<SqlToken> $tokens
     */
    private static function isIdentifierSuffix(array $tokens, int $index): bool
    {
        return match (count($tokens) - $index) {
            1 => $tokens[$index]->kind === SqlTokenKind::Word
                || $tokens[$index]->kind === SqlTokenKind::QuotedIdentifier,
            3 => self::isSymbol($tokens[$index], '[')
                && $tokens[$index + 1]->kind === SqlTokenKind::Word
                && self::isSymbol($tokens[$index + 2], ']'),
            default => false,
        };
    }

    private static function isSymbol(SqlToken $token, string $symbol): bool
    {
        return $token->kind === SqlTokenKind::Symbol && $token->text === $symbol;
    }
}
