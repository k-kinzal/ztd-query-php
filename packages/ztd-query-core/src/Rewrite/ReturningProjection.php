<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * A validated projection for simple SQL RETURNING column lists.
 */
final class ReturningProjection
{
    /**
     * @param list<array{source: string|null, output: string|null}> $items
     */
    private function __construct(private readonly array $items)
    {
    }

    public static function parse(string $sql): ?self
    {
        $clause = SqlTokenStream::tokenize($sql)->topLevelClause(['RETURNING']);
        if ($clause === null) {
            return null;
        }

        $items = [];
        foreach (SqlTokenStream::tokenize(rtrim($clause, "; \t\n\r\0\x0B"))->splitTopLevel() as $expression) {
            $item = self::parseItem($expression);
            if ($item === null) {
                throw new UnsupportedSqlException(
                    $sql,
                    'RETURNING supports columns, qualified columns, aliases, and wildcard projections',
                );
            }
            $items[] = $item;
        }

        if ($items === []) {
            throw new UnsupportedSqlException($sql, 'RETURNING requires a projection');
        }

        return new self($items);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public function project(array $rows): array
    {
        $projectedRows = [];
        foreach ($rows as $row) {
            $projected = [];
            foreach ($this->items as $item) {
                if ($item['source'] === null) {
                    $projected = array_merge($projected, $row);
                    continue;
                }
                $output = $item['output'] ?? $item['source'];
                $projected[$output] = $row[$item['source']] ?? null;
            }
            $projectedRows[] = $projected;
        }

        return $projectedRows;
    }

    /**
     * @return array{source: string|null, output: string|null}|null
     */
    private static function parseItem(string $expression): ?array
    {
        $tokens = SqlTokenStream::tokenize($expression)->significantTokens();
        $alias = null;
        $asIndex = self::asIndex($tokens);
        if ($asIndex !== null) {
            $aliasToken = $tokens[$asIndex + 1];
            if (!self::isIdentifier($aliasToken) || $asIndex + 2 !== count($tokens)) {
                return null;
            }
            $alias = self::unquote($aliasToken->text);
            $tokens = array_slice($tokens, 0, $asIndex);
        }

        if (count($tokens) === 1 && $tokens[0]->kind === SqlTokenKind::Symbol && $tokens[0]->text === '*') {
            return ['source' => null, 'output' => null];
        }

        if (!self::isIdentifierPath($tokens)) {
            return null;
        }
        $last = $tokens[count($tokens) - 1];
        if ($last->kind === SqlTokenKind::Symbol && $last->text === '*') {
            return ['source' => null, 'output' => null];
        }

        $source = self::unquote($last->text);

        return ['source' => $source, 'output' => $alias];
    }

    /** @param list<SqlToken> $tokens */
    private static function asIndex(array $tokens): ?int
    {
        foreach ($tokens as $index => $token) {
            if ($token->isKeyword('AS')) {
                return $index;
            }
        }

        return null;
    }

    /** @param list<SqlToken> $tokens */
    private static function isIdentifierPath(array $tokens): bool
    {
        if ($tokens === []) {
            return false;
        }
        foreach ($tokens as $index => $token) {
            if ($index % 2 === 0) {
                $isTrailingWildcard = $index === count($tokens) - 1
                    && $token->kind === SqlTokenKind::Symbol
                    && $token->text === '*';
                if (!self::isIdentifier($token) && !$isTrailingWildcard) {
                    return false;
                }
                continue;
            }
            if ($token->kind !== SqlTokenKind::Symbol || $token->text !== '.') {
                return false;
            }
        }

        return count($tokens) % 2 === 1;
    }

    private static function isIdentifier(SqlToken $token): bool
    {
        return in_array($token->kind, [SqlTokenKind::Word, SqlTokenKind::QuotedIdentifier], true);
    }

    private static function unquote(string $identifier): string
    {
        $first = $identifier[0] ?? '';
        $last = $identifier[strlen($identifier) - 1] ?? '';
        if (($first === '"' && $last === '"') || ($first === '`' && $last === '`')) {
            return str_replace($first . $first, $first, substr($identifier, 1, -1));
        }
        if ($first === '[' && $last === ']') {
            return str_replace(']]', ']', substr($identifier, 1, -1));
        }

        return $identifier;
    }
}
