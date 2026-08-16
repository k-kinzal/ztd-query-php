<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Restores native operators that cannot be represented by a CTE column cast.
 */
final class MySqlTypeSemantics
{
    /**
     * @param array<string, array{
     *     rows: array<int, array<string, mixed>>,
     *     columns: array<int, string>,
     *     columnTypes: array<string, ColumnType>
     * }> $tables
     */
    public function rewrite(string $sql, array $tables): string
    {
        [$qualified, $unqualified] = $this->enumColumns($tables);
        if ($qualified === [] && $unqualified === []) {
            return $sql;
        }

        $tokens = SqlTokenStream::tokenize($sql)->significantTokens();
        $edits = $this->comparisonEdits($sql, $tokens, $qualified, $unqualified);
        foreach ($this->orderByEdits($sql, $tokens, $qualified, $unqualified) as $key => $edit) {
            $edits[$key] = $edit;
        }

        uasort($edits, static fn (array $left, array $right): int => $right['start'] <=> $left['start']);
        foreach ($edits as $edit) {
            $sql = substr($sql, 0, $edit['start']) . $edit['replacement'] . substr($sql, $edit['end']);
        }

        return $sql;
    }

    /**
     * @param array<string, array{
     *     rows: array<int, array<string, mixed>>,
     *     columns: array<int, string>,
     *     columnTypes: array<string, ColumnType>
     * }> $tables
     * @return array{array<string, list<string>>, array<string, list<string>|null>}
     */
    private function enumColumns(array $tables): array
    {
        $qualified = [];
        $unqualified = [];
        foreach ($tables as $table => $context) {
            foreach ($context['columnTypes'] as $column => $type) {
                $members = $this->enumMembers($type->nativeType);
                if ($members === []) {
                    continue;
                }
                $qualified[strtolower("$table.$column")] = $members;
                $key = strtolower($column);
                if (!array_key_exists($key, $unqualified)) {
                    $unqualified[$key] = $members;
                } elseif ($unqualified[$key] !== $members) {
                    $unqualified[$key] = null;
                }
            }
        }

        return [$qualified, $unqualified];
    }

    /**
     * @param list<SqlToken> $tokens
     * @param array<string, list<string>> $qualified
     * @param array<string, list<string>|null> $unqualified
     * @return array<string, array{start: int, end: int, replacement: string}>
     */
    private function comparisonEdits(string $sql, array $tokens, array $qualified, array $unqualified): array
    {
        $edits = [];
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $column = $this->columnAt($sql, $tokens, $index, $qualified, $unqualified);
            if ($column === null) {
                continue;
            }
            $operatorIndex = $index + $column['length'];
            $operator = $this->orderedOperatorAt($tokens, $operatorIndex);
            if ($operator === null) {
                continue;
            }
            $value = $tokens[$operatorIndex + $operator['length']] ?? null;
            if ($value === null || $value->kind !== SqlTokenKind::String) {
                continue;
            }

            $this->addRankEdit($edits, $column['token'], $column['members']);
            $this->addRankEdit($edits, $value, $column['members']);
            $index = $operatorIndex + $operator['length'];
        }

        return $edits;
    }

    /**
     * @param list<SqlToken> $tokens
     * @param array<string, list<string>> $qualified
     * @param array<string, list<string>|null> $unqualified
     * @return array<string, array{start: int, end: int, replacement: string}>
     */
    private function orderByEdits(string $sql, array $tokens, array $qualified, array $unqualified): array
    {
        $edits = [];
        for ($index = 0, $count = count($tokens); $index < $count - 1; $index++) {
            if (!$tokens[$index]->isKeyword('ORDER') || !$tokens[$index + 1]->isKeyword('BY')) {
                continue;
            }
            $depth = $tokens[$index]->depth;
            $bracketDepth = $tokens[$index]->bracketDepth;
            for ($item = $index + 2; $item < $count; $item++) {
                $token = $tokens[$item];
                if ($token->depth < $depth || $token->bracketDepth < $bracketDepth) {
                    break;
                }
                if ($token->depth !== $depth || $token->bracketDepth !== $bracketDepth) {
                    continue;
                }
                if ($token->isKeyword('LIMIT') || $token->isKeyword('FOR') || $token->isKeyword('LOCK')) {
                    break;
                }
                $column = $this->columnAt($sql, $tokens, $item, $qualified, $unqualified);
                if ($column === null) {
                    continue;
                }
                $after = $tokens[$item + $column['length']] ?? null;
                if ($after !== null
                    && $after->depth === $depth
                    && $after->bracketDepth === $bracketDepth
                    && !$after->isKeyword('ASC')
                    && !$after->isKeyword('DESC')
                    && !($after->kind === SqlTokenKind::Symbol && $after->text === ',')
                    && !$after->isKeyword('LIMIT')
                    && !$after->isKeyword('FOR')
                    && !$after->isKeyword('LOCK')
                ) {
                    continue;
                }
                $this->addRankEdit($edits, $column['token'], $column['members']);
                $item += $column['length'] - 1;
            }
        }

        return $edits;
    }

    /**
     * @param list<SqlToken> $tokens
     * @param array<string, list<string>> $qualified
     * @param array<string, list<string>|null> $unqualified
     * @return array{token: SqlToken, length: int, members: list<string>}|null
     */
    private function columnAt(string $sql, array $tokens, int $index, array $qualified, array $unqualified): ?array
    {
        $first = $tokens[$index] ?? null;
        if ($first === null || !$this->isIdentifier($first)) {
            return null;
        }

        $length = 1;
        $column = $this->unquoteIdentifier($first->text);
        $qualifiedKey = null;
        if (($tokens[$index + 1] ?? null)?->text === '.' && isset($tokens[$index + 2]) && $this->isIdentifier($tokens[$index + 2])) {
            $length = 3;
            $column = $this->unquoteIdentifier($tokens[$index + 2]->text);
            $qualifiedKey = strtolower($this->unquoteIdentifier($first->text) . '.' . $column);
        }

        $members = $qualifiedKey !== null ? ($qualified[$qualifiedKey] ?? null) : null;
        $members ??= $unqualified[strtolower($column)] ?? null;
        if ($members === null) {
            return null;
        }

        $last = $tokens[$index + $length - 1];

        return [
            'token' => new SqlToken(
                SqlTokenKind::Word,
                substr($sql, $first->offset, $last->endOffset() - $first->offset),
                $first->offset,
                $first->depth,
                $first->bracketDepth,
            ),
            'length' => $length,
            'members' => $members,
        ];
    }

    /**
     * @param list<SqlToken> $tokens
     * @return array{length: int}|null
     */
    private function orderedOperatorAt(array $tokens, int $index): ?array
    {
        $first = $tokens[$index] ?? null;
        if ($first === null || $first->kind !== SqlTokenKind::Symbol || ($first->text !== '<' && $first->text !== '>')) {
            return null;
        }
        $second = $tokens[$index + 1] ?? null;

        return ['length' => $second?->text === '=' ? 2 : 1];
    }

    /**
     * @param array<string, array{start: int, end: int, replacement: string}> $edits
     * @param list<string> $members
     */
    private function addRankEdit(array &$edits, SqlToken $token, array $members): void
    {
        $memberSql = array_map(
            static fn (string $member): string => "'" . str_replace("'", "''", $member) . "'",
            $members,
        );
        $key = $token->offset . ':' . $token->endOffset();
        $edits[$key] = [
            'start' => $token->offset,
            'end' => $token->endOffset(),
            'replacement' => 'FIELD(' . $token->text . ', ' . implode(', ', $memberSql) . ')',
        ];
    }

    /** @return list<string> */
    private function enumMembers(string $nativeType): array
    {
        $open = strpos($nativeType, '(');
        if ($open === false || strtoupper(trim(substr($nativeType, 0, $open))) !== 'ENUM' || !str_ends_with(trim($nativeType), ')')) {
            return [];
        }
        $inner = substr(trim($nativeType), $open + 1, -1);
        $members = [];
        foreach (SqlTokenStream::tokenize($inner)->splitTopLevel() as $literal) {
            $literal = trim($literal);
            if (strlen($literal) < 2) {
                return [];
            }
            $quote = $literal[0];
            if (($quote !== "'" && $quote !== '"') || $literal[strlen($literal) - 1] !== $quote) {
                return [];
            }
            $value = substr($literal, 1, -1);
            $members[] = str_replace([$quote . $quote, '\\' . $quote, '\\\\'], [$quote, $quote, '\\'], $value);
        }

        return $members;
    }

    private function isIdentifier(SqlToken $token): bool
    {
        return $token->kind === SqlTokenKind::Word || $token->kind === SqlTokenKind::QuotedIdentifier;
    }

    private function unquoteIdentifier(string $identifier): string
    {
        if ((str_starts_with($identifier, '`') && str_ends_with($identifier, '`'))
            || (str_starts_with($identifier, '"') && str_ends_with($identifier, '"'))
        ) {
            return substr($identifier, 1, -1);
        }

        return $identifier;
    }
}
