<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite;

use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Replaces FTS virtual-table operators with expressions executable over shadow CTE rows.
 */
final class SqliteFullTextSearchRewriter
{
    private SqliteIdentifierQuoter $quoter;

    public function __construct()
    {
        $this->quoter = new SqliteIdentifierQuoter();
    }

    /**
     * @param array<string, array<string, mixed>> $tables
     */
    public function rewrite(string $sql, array $tables): string
    {
        $tokens = SqlTokenStream::tokenize($sql)->significantTokens();
        /** @var list<array{start: int, end: int, replacement: string}> $edits */
        $edits = [];

        foreach ($tokens as $index => $operator) {
            $isMatch = $operator->isKeyword('MATCH');
            $isEquals = $operator->kind === SqlTokenKind::Symbol && $operator->text === '=';
            if (!$isMatch && !$isEquals) {
                continue;
            }
            $left = $tokens[$index - 1] ?? null;
            $query = $tokens[$index + 1] ?? null;
            if ($left === null || $query === null || !self::isIdentifier($left) || !self::isQueryExpression($query)) {
                continue;
            }
            $name = self::identifierName($left);
            $columns = $this->tableColumns($name, $tables);
            if ($columns === null && $isMatch) {
                $columns = $this->matchingColumn($name, $tables);
            }
            if ($columns === null || $columns === []) {
                continue;
            }

            $documentParts = array_map(
                fn (string $column): string => "COALESCE(CAST({$this->quoter->quote($column)} AS TEXT), '')",
                $columns,
            );
            $document = 'LOWER(' . implode(" || ' ' || ", $documentParts) . ')';
            $needle = "LOWER(NULLIF(TRIM(CAST(({$query->text}) AS TEXT)), ''))";
            $edits[] = [
                'start' => $left->offset,
                'end' => $query->endOffset(),
                'replacement' => "(INSTR($document, $needle) > 0)",
            ];
        }

        usort($edits, static fn (array $left, array $right): int => $right['start'] <=> $left['start']);
        foreach ($edits as $edit) {
            $sql = substr_replace($sql, $edit['replacement'], $edit['start'], $edit['end'] - $edit['start']);
        }

        return $sql;
    }

    /**
     * @param array<string, array<string, mixed>> $tables
     * @return list<string>|null
     */
    private function tableColumns(string $name, array $tables): ?array
    {
        foreach ($tables as $tableName => $context) {
            if (strcasecmp($name, $tableName) !== 0 || isset($context['viewSql'])) {
                continue;
            }
            $columns = $context['columns'] ?? null;
            if (!is_array($columns)) {
                return null;
            }

            return array_values(array_filter($columns, 'is_string'));
        }

        return null;
    }

    /**
     * @param array<string, array<string, mixed>> $tables
     * @return list<string>|null
     */
    private function matchingColumn(string $name, array $tables): ?array
    {
        $match = null;
        foreach ($tables as $context) {
            if (isset($context['viewSql'])) {
                continue;
            }
            $columns = $context['columns'] ?? null;
            if (!is_array($columns)) {
                continue;
            }
            foreach ($columns as $column) {
                if (!is_string($column) || strcasecmp($name, $column) !== 0) {
                    continue;
                }
                if ($match !== null) {
                    return null;
                }
                $match = [$column];
            }
        }

        return $match;
    }

    private static function isIdentifier(SqlToken $token): bool
    {
        return $token->kind === SqlTokenKind::Word || $token->kind === SqlTokenKind::QuotedIdentifier;
    }

    private static function isQueryExpression(SqlToken $token): bool
    {
        return in_array($token->kind, [SqlTokenKind::String, SqlTokenKind::Parameter], true);
    }

    private static function identifierName(SqlToken $token): string
    {
        if ($token->kind !== SqlTokenKind::QuotedIdentifier || strlen($token->text) < 2) {
            return $token->text;
        }

        return str_replace('""', '"', substr($token->text, 1, -1));
    }
}
