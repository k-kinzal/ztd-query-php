<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite;

use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Replaces FTS virtual-table operators with expressions executable over shadow CTE rows.
 *
 * @phpstan-type TableContext array{viewSql: string}|array{columns: array<int, string>}
 */
final class SqliteFullTextSearchRewriter
{
    private SqliteIdentifierQuoter $quoter;
    private SqliteParser $parser;

    /**
     * Binds the instance to what it will work from.
     *
     */
    public function __construct()
    {
        $this->quoter = new SqliteIdentifierQuoter();
        $this->parser = new SqliteParser();
    }

    /**
     * @param array<string, TableContext> $tables
     */
    public function rewrite(string $sql, array $tables): string
    {
        $stream = SqlTokenStream::tokenize($sql, SqliteLexerProfile::create());
        /** @var list<array{start: int, end: int, replacement: string}> $edits */
        $edits = [];

        foreach ($stream->significantTokens() as $operator) {
            $edit = $this->expressionEdit($stream, $operator, $tables);
            if ($edit === null) {
                continue;
            }
            $edits[] = $edit;
        }

        usort($edits, static fn (array $left, array $right): int => $right['start'] <=> $left['start']);
        foreach ($edits as $edit) {
            $sql = substr_replace($sql, $edit['replacement'], $edit['start'], $edit['end'] - $edit['start']);
        }

        return $sql;
    }

    /**
     * Answers the edit that replaces one MATCH with what it comes to.
     *
     * There is no full-text index in the shadow, so a match is worked out from
     * the columns themselves.
     *
     * @param SqlTokenStream $stream Stream to read
     * @param SqlToken $operator The operator
     * @param array<string, TableContext> $tables The tables
     *
     * @return array{start: int, end: int, replacement: string}|null What it answers
     */
    public function expressionEdit(SqlTokenStream $stream, SqlToken $operator, array $tables): ?array
    {
        if ($operator->isKeyword('MATCH')) {
            $allowsColumnName = true;
        } elseif ($operator->kind === SqlTokenKind::Symbol && $operator->text === '=') {
            $allowsColumnName = false;
        } else {
            return null;
        }

        $left = $stream->significantTokenBefore($operator);
        if ($left === null || !self::isIdentifier($left)) {
            return null;
        }
        $query = $stream->significantTokenAfter($operator);
        if ($query === null || !self::isQueryExpression($query)) {
            return null;
        }

        $name = $this->parser->unquoteIdentifier($left->text);
        $columns = $this->tableColumns($name, $tables);
        if ($columns === null && $allowsColumnName) {
            $columns = $this->matchingColumn($name, $tables);
        }
        if ($columns === null || $columns === []) {
            return null;
        }

        $documentParts = array_map(
            fn (string $column): string => "COALESCE(CAST({$this->quoter->quote($column)} AS TEXT), '')",
            $columns,
        );
        $document = 'LOWER(' . implode(" || ' ' || ", $documentParts) . ')';
        $needle = "LOWER(NULLIF(TRIM(CAST(({$query->text}) AS TEXT)), ''))";

        return [
            'start' => $left->offset,
            'end' => $query->endOffset(),
            'replacement' => "(INSTR($document, $needle) > 0)",
        ];
    }

    /**
     * Answers the columns a table has, if the shadow knows it.
     *
     * @param string $name Name to read
     * @param array<string, TableContext> $tables The tables
     *
     * @return array<int, string>|null What it answers
     */
    public function tableColumns(string $name, array $tables): ?array
    {
        foreach ($tables as $tableName => $context) {
            if (strcasecmp($name, $tableName) !== 0) {
                continue;
            }
            if (isset($context['viewSql'])) {
                return null;
            }

            return $context['columns'];
        }

        return null;
    }

    /**
     * Answers the column a match is against, and the table it belongs to.
     *
     * @param string $name Name to read
     * @param array<string, TableContext> $tables The tables
     *
     * @return list<string>|null What it answers
     */
    public function matchingColumn(string $name, array $tables): ?array
    {
        $match = null;
        foreach ($tables as $context) {
            if (isset($context['viewSql'])) {
                continue;
            }
            foreach ($context['columns'] as $column) {
                if (strcasecmp($name, $column) !== 0) {
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

    /**
     * Reports whether a token is a name at all.
     *
     * @param SqlToken $token Token to read
     *
     * @return bool What it answers
     */
    public static function isIdentifier(SqlToken $token): bool
    {
        return $token->kind === SqlTokenKind::Word || $token->kind === SqlTokenKind::QuotedIdentifier;
    }

    /**
     * Reports whether a token could be what is searched for.
     *
     * @param SqlToken $token Token to read
     *
     * @return bool What it answers
     */
    public static function isQueryExpression(SqlToken $token): bool
    {
        return in_array($token->kind, [SqlTokenKind::String, SqlTokenKind::Parameter], true);
    }
}
