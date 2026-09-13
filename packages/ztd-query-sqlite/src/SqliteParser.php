<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite;

/**
 * Lightweight SQL parser for SQLite.
 *
 * Uses lexical statement classification and focused extraction for the SQL subset needed by ZTD:
 * SELECT, INSERT, UPDATE, DELETE, CREATE TABLE, DROP TABLE, ALTER TABLE ADD COLUMN.
 *
 * Returns structured representations of parsed statements.
 */
final class SqliteParser
{
    /**
     * Classify the type of a SQL statement.
     *
     * @return string|null Statement type: 'SELECT', 'INSERT', 'UPDATE', 'DELETE',
     *                     'CREATE_TABLE', 'DROP_TABLE', 'ALTER_TABLE', or null if unsupported.
     *
     * @visibility public
     * @example Classify a statement following a CTE
     *     $parser = new \ZtdQuery\Platform\Sqlite\SqliteParser();
     *     $parser->classifyStatement('WITH ids AS (SELECT 1) SELECT * FROM ids') // => 'SELECT'
     *     $parser->classifyStatement('VACUUM') // => null
     */
    public function classifyStatement(string $sql): ?string
    {
        return (new Parsing\Statement\StatementClassifier())->classifyStatement($sql);
    }

    /**
     * Split a SQL string into individual statements.
     *
     * @return list<string>
     *
     * @visibility public
     * @example Split statements without splitting quoted semicolons
     *     (new \ZtdQuery\Platform\Sqlite\SqliteParser())->splitStatements("SELECT ';'; SELECT 2") // => ["SELECT ';'", 'SELECT 2']
     */
    public function splitStatements(string $sql): array
    {
        return (new Parsing\Statement\StatementStructure())->splitStatements($sql);
    }

    /**
     * Extract the target table name from a DML statement.
     */
    public function extractTargetTable(string $sql): ?string
    {
        return (new Parsing\Statement\TargetTableParser())->extractTargetTable($sql);
    }

    /**
     * Extract table names referenced in a SELECT statement.
     *
     * @return array<int, string>
     */
    public function extractSelectTables(string $sql): array
    {
        return (new Parsing\Statement\StatementStructure())->extractSelectTables($sql);
    }

    /**
     * Extract columns from an INSERT statement.
     *
     * @return array<int, string>
     */
    public function extractInsertColumns(string $sql): array
    {
        return (new Parsing\Insert\InsertClauseParser())->extractInsertColumns($sql);
    }

    /**
     * Extract VALUES from an INSERT statement.
     *
     * @return array<int, array<int, string>>
     */
    public function extractInsertValues(string $sql): array
    {
        return (new Parsing\Insert\InsertClauseParser())->extractInsertValues($sql);
    }

    /**
     * Extract SET assignments from an UPDATE statement.
     *
     * @return array<string, string> Column name => value expression.
     */
    public function extractUpdateAssignments(string $sql): array
    {
        return (new Parsing\Expression\AssignmentParser())->extractUpdateAssignments($sql);
    }

    /**
     * Returns the target alias preceding an UPDATE SET clause.
     */
    public function extractUpdateAlias(string $sql): ?string
    {
        return (new Parsing\Update\UpdateClauseParser())->extractUpdateAlias($sql);
    }

    /**
     * Returns the UPDATE FROM source before filtering clauses.
     */
    public function extractUpdateFromClause(string $sql): ?string
    {
        return (new Parsing\Update\UpdateClauseParser())->extractUpdateFromClause($sql);
    }

    /**
     * Extract WHERE clause from a DML statement.
     */
    public function extractWhereClause(string $sql): ?string
    {
        return (new Parsing\Update\UpdateClauseParser())->extractWhereClause($sql);
    }

    /**
     * Extract ORDER BY clause from a statement.
     */
    public function extractOrderByClause(string $sql): ?string
    {
        return (new Parsing\Update\UpdateClauseParser())->extractOrderByClause($sql);
    }

    /**
     * Extract LIMIT clause from a statement.
     */
    public function extractLimitClause(string $sql): ?string
    {
        return (new Parsing\Update\UpdateClauseParser())->extractLimitClause($sql);
    }

    /**
     * Check if an INSERT statement has ON CONFLICT clause (upsert).
     */
    public function hasOnConflict(string $sql): bool
    {
        return (new Parsing\Insert\InsertClauseParser())->hasOnConflict($sql);
    }

    /**
     * Check if the statement is INSERT OR REPLACE / REPLACE INTO.
     */
    public function isReplace(string $sql): bool
    {
        return (new Parsing\Insert\InsertClauseParser())->isReplace($sql);
    }

    /**
     * Check if the statement is INSERT OR IGNORE / INSERT IGNORE.
     */
    public function isInsertIgnore(string $sql): bool
    {
        return (new Parsing\Insert\InsertClauseParser())->isInsertIgnore($sql);
    }

    /**
     * Extract ON CONFLICT update columns from an upsert statement.
     *
     * @return array<string, string> Column name => value expression.
     */
    public function extractOnConflictUpdates(string $sql): array
    {
        return (new Parsing\Expression\AssignmentParser())->extractOnConflictUpdates($sql);
    }

    /**
     * Returns the predicate belonging to DO UPDATE SET.
     */
    public function extractOnConflictUpdateWhere(string $sql): ?string
    {
        return (new Parsing\Update\UpdateClauseParser())->extractOnConflictUpdateWhere($sql);
    }

    /**
     * Check if an INSERT has a SELECT subquery.
     */
    public function hasInsertSelect(string $sql): bool
    {
        return (new Parsing\Insert\InsertClauseParser())->hasInsertSelect($sql);
    }

    /**
     * Extract the SELECT subquery from an INSERT ... SELECT statement.
     */
    public function extractInsertSelect(string $sql): ?string
    {
        return (new Parsing\Insert\InsertClauseParser())->extractInsertSelect($sql);
    }

    /**
     * Strip SQL comments from a string.
     */
    public function stripComments(string $sql): string
    {
        return (new Parsing\Lexing\LiteralMasker())->stripComments($sql);
    }

    /**
     * Replaces single-quoted literal spans with spaces at the same offsets.
     */
    public function maskStringLiterals(string $sql): string
    {
        return (new Parsing\Lexing\LiteralMasker())->maskStringLiterals($sql);
    }

    /**
     * Unquote a SQL identifier (double-quoted or backtick-quoted).
     */
    public function unquoteIdentifier(string $identifier): string
    {
        return (new Parsing\Lexing\IdentifierDecoder())->unquoteIdentifier($identifier);
    }
}
