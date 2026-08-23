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
     * @param array<string, array{viewSql: string}|array{
     *     rows: array<int, array<string, mixed>>,
     *     columns: array<int, string>,
     *     columnTypes: array<string, ColumnType>
     * }> $tables
     */
    public function rewrite(string $sql, array $tables): string
    {
        [$qualified, $unqualified] = $this->enumColumns($tables);
        $tokens = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->significantTokens();
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
     * @param array<string, array{viewSql: string}|array{
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
            if (isset($context['viewSql'])) {
                continue;
            }
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
     * @return array<int, array{start: int, end: int, replacement: string}>
     */
    private function comparisonEdits(string $sql, array $tokens, array $qualified, array $unqualified): array
    {
        $edits = [];
        foreach ($tokens as $index => $token) {
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
        }

        return $edits;
    }

    /**
     * @param list<SqlToken> $tokens
     * @param array<string, list<string>> $qualified
     * @param array<string, list<string>|null> $unqualified
     * @return array<int, array{start: int, end: int, replacement: string}>
     */
    private function orderByEdits(string $sql, array $tokens, array $qualified, array $unqualified): array
    {
        $edits = [];
        foreach ($tokens as $index => $order) {
            if (!$order->isKeyword('ORDER')) {
                continue;
            }
            $by = $tokens[$index + 1] ?? null;
            if ($by === null || !$by->isKeyword('BY')) {
                continue;
            }
            $depth = $order->depth;
            $bracketDepth = $order->bracketDepth;
            $afterBy = false;
            foreach ($tokens as $item => $token) {
                if (!$afterBy) {
                    $afterBy = $token === $by;
                    continue;
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
        $first = $tokens[$index];
        if (($tokens[$index - 1] ?? null)?->text === '.') {
            return null;
        }

        $length = 1;
        $column = $this->identifierName($first);
        $qualifiedKey = null;
        $dot = $tokens[$index + 1] ?? null;
        $qualifiedColumn = $tokens[$index + 2] ?? null;
        if ($dot?->text === '.' && $qualifiedColumn !== null && $this->isIdentifier($qualifiedColumn)) {
            $length = 3;
            $column = $this->identifierName($qualifiedColumn);
            $qualifiedKey = strtolower($this->identifierName($first) . '.' . $column);
        }

        $members = $qualifiedKey === null
            ? ($unqualified[strtolower($column)] ?? null)
            : ($qualified[$qualifiedKey] ?? null);
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
     * @param array<int, array{start: int, end: int, replacement: string}> $edits
     * @param list<string> $members
     */
    private function addRankEdit(array &$edits, SqlToken $token, array $members): void
    {
        $memberSql = array_map(
            static fn (string $member): string => "'" . str_replace(['\\', "'"], ['\\\\', "''"], $member) . "'",
            $members,
        );
        $edits[$token->offset] = [
            'start' => $token->offset,
            'end' => $token->endOffset(),
            'replacement' => 'FIELD(' . $token->text . ', ' . implode(', ', $memberSql) . ')',
        ];
    }

    /** @return list<string> */
    private function enumMembers(string $nativeType): array
    {
        $open = strpos($nativeType, '(');
        if ($open === false) {
            return [];
        }
        if (strtoupper(trim(substr($nativeType, 0, $open))) !== 'ENUM') {
            return [];
        }
        $inner = substr(trim($nativeType), $open + 1, -1);
        $members = [];
        foreach (SqlTokenStream::tokenize($inner, MySqlLexerProfile::create())->splitTopLevel() as $literal) {
            if (strlen($literal) < 2) {
                return [];
            }
            $quote = $literal[0];
            if ($quote !== "'" && $quote !== '"') {
                return [];
            }
            if ($literal[strlen($literal) - 1] !== $quote) {
                return [];
            }
            $value = substr($literal, 1, -1);
            $members[] = str_replace([$quote . $quote, '\\' . $quote, '\\\\'], [$quote, $quote, '\\'], $value);
        }

        return $members;
    }

    private function isIdentifier(SqlToken $token): bool
    {
        return in_array($token->kind, [SqlTokenKind::Word, SqlTokenKind::QuotedIdentifier], true);
    }

    private function identifierName(SqlToken $token): string
    {
        return $token->kind === SqlTokenKind::QuotedIdentifier
            ? substr($token->text, 1, -1)
            : $token->text;
    }
}
