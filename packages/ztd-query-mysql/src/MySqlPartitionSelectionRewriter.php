<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\TablePartitioning;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Rewrites explicit MySQL partition selection into filtered table sources.
 */
final class MySqlPartitionSelectionRewriter
{
    /**
     * @param array<string, array{viewSql: string}|array{
     *     rows: array<int, array<string, mixed>>,
     *     columns: array<int, string>,
     *     columnTypes: array<string, ColumnType>,
     *     partitioning?: TablePartitioning|null
     * }> $tables
     */
    public function rewrite(string $sql, array $tables): string
    {
        $stream = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create());
        $tokens = $stream->significantTokens();
        $contexts = [];
        foreach ($tables as $table => $context) {
            $contexts[strtolower($table)] = $context;
        }

        $edits = [];
        foreach ((new MySqlSelectRelationParser())->references($sql) as $reference) {
            $partitionIndex = $this->tokenIndexAtOrAfter($tokens, $reference['end']);
            $partition = $tokens[$partitionIndex] ?? null;
            if (!$partition instanceof SqlToken || !$partition->isKeyword('PARTITION')) {
                continue;
            }

            $open = $tokens[$partitionIndex + 1] ?? null;
            if (!$open instanceof SqlToken || !$this->isSymbol($open, '(')) {
                throw new UnsupportedSqlException($sql, 'PARTITION selection opening parenthesis');
            }
            $closeIndex = $this->closingParenthesisIndex($tokens, $open);
            if ($closeIndex === null) {
                throw new UnsupportedSqlException($sql, 'PARTITION selection closing parenthesis');
            }

            $names = $this->partitionNames($sql, $open, $tokens[$closeIndex]);
            $context = $contexts[strtolower($reference['name'])] ?? null;
            $partitioning = $context['partitioning'] ?? null;
            $predicates = $partitioning instanceof TablePartitioning
                ? $partitioning->predicatesFor($names)
                : null;
            if ($predicates === null) {
                throw new UnsupportedSqlException($sql, 'PARTITION selection');
            }
            $predicate = implode(' OR ', array_map(
                static fn (string $partitionPredicate): string => "($partitionPredicate)",
                $predicates,
            ));

            $after = $tokens[$closeIndex + 1] ?? null;
            if ($after instanceof SqlToken
                && in_array(strtoupper($after->text), ['USE', 'FORCE', 'IGNORE'], true)
            ) {
                throw new UnsupportedSqlException($sql, 'PARTITION selection with index hint');
            }

            $tableSql = substr(
                $sql,
                $reference['unqualifiedStart'],
                $reference['end'] - $reference['unqualifiedStart'],
            );
            $replacement = "(SELECT * FROM $tableSql WHERE $predicate)";
            if (!$this->hasAlias($tokens, $closeIndex + 1)) {
                $replacement .= " AS $tableSql";
            }
            $edits[] = [
                'start' => $reference['start'],
                'end' => $tokens[$closeIndex]->endOffset(),
                'replacement' => $replacement,
            ];
        }

        usort($edits, static fn (array $left, array $right): int => $right['start'] <=> $left['start']);
        foreach ($edits as $edit) {
            $sql = substr_replace($sql, $edit['replacement'], $edit['start'], $edit['end'] - $edit['start']);
        }

        return $sql;
    }

    /** @param list<SqlToken> $tokens */
    private function tokenIndexAtOrAfter(array $tokens, int $offset): int
    {
        $afterReference = false;
        foreach ($tokens as $index => $token) {
            if ($afterReference) {
                return $index;
            }
            $afterReference = $token->endOffset() === $offset;
        }

        return count($tokens);
    }

    /** @param list<SqlToken> $tokens */
    private function closingParenthesisIndex(array $tokens, SqlToken $open): ?int
    {
        $afterOpen = false;
        foreach ($tokens as $index => $token) {
            if ($token === $open) {
                $afterOpen = true;
            } elseif ($afterOpen && $this->isSymbol($token, ')') && $token->depth === $open->depth) {
                return $index;
            }
        }

        return null;
    }

    /** @return non-empty-list<string> */
    private function partitionNames(string $sql, SqlToken $open, SqlToken $close): array
    {
        $list = substr($sql, $open->endOffset(), $close->offset - $open->endOffset());
        $names = [];
        foreach (SqlTokenStream::tokenize($list, MySqlLexerProfile::create())->splitTopLevel() as $part) {
            $stream = SqlTokenStream::tokenize($part, MySqlLexerProfile::create());
            $identifier = $stream->identifierAt();
            if ($identifier === null) {
                throw new UnsupportedSqlException($sql, 'PARTITION selection');
            }
            if ($identifier['next'] !== count($stream->significantTokens())) {
                throw new UnsupportedSqlException($sql, 'PARTITION selection');
            }
            $names[] = $identifier['name'];
        }
        if ($names === []) {
            throw new UnsupportedSqlException($sql, 'PARTITION selection');
        }

        return $names;
    }

    /** @param list<SqlToken> $tokens */
    private function hasAlias(array $tokens, int $index): bool
    {
        $token = $tokens[$index] ?? null;
        if ($token === null) {
            return false;
        }
        if (!$this->isIdentifier($token)) {
            return false;
        }

        return !in_array(
            strtoupper($token->text),
            ['JOIN', 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'CROSS', 'STRAIGHT_JOIN', 'WHERE', 'GROUP', 'HAVING', 'ORDER', 'LIMIT', 'OFFSET', 'UNION', 'INTERSECT', 'EXCEPT', 'FOR', 'LOCK', 'ON', 'USING'],
            true,
        );
    }

    private function isIdentifier(SqlToken $token): bool
    {
        return in_array($token->kind, [SqlTokenKind::Word, SqlTokenKind::QuotedIdentifier], true);
    }

    private function isSymbol(SqlToken $token, string $symbol): bool
    {
        return $token->kind === SqlTokenKind::Symbol && $token->text === $symbol;
    }
}
