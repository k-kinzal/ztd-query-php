<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Sql\SqlTokenStream;

/**
 * Focused PostgreSQL SQL parser.
 *
 * Handles the ZTD-required SQL subset: SELECT, INSERT, UPDATE, DELETE,
 * CREATE TABLE, DROP TABLE, ALTER TABLE, TRUNCATE.
 *
 * Uses regex + recursive descent hybrid approach to extract structural
 * information without a full PostgreSQL grammar parser.
 */
final class PgSqlParser
{
    /**
     * Classify a SQL statement type.
     *
     * @return 'SELECT'|'INSERT'|'UPDATE'|'DELETE'|'MERGE'|'TRUNCATE'|'CREATE_TABLE'|'DROP_TABLE'|'ALTER_TABLE'|'DO'|'TCL'|null
     *
     * @visibility public
     * @example Classify the write following a common table expression
     *     (new \ZtdQuery\Platform\Postgres\PgSqlParser())->classifyStatement('WITH ids AS (SELECT 1) DELETE FROM users WHERE id IN (SELECT * FROM ids)') // => 'DELETE'
     */
    public function classifyStatement(string $sql): ?string
    {
        $trimmed = PostgreSqlLexicalMasker::maskComments($sql);

        if (preg_match('/^\s*WITH\b/i', $trimmed) === 1) {
            return (new Parsing\Statement\Classification())->classifyWithStatement($trimmed);
        }

        return (new Parsing\Statement\Classification())->classifySimpleStatement($trimmed);
    }

    /**
     * Split SQL string into individual statements.
     *
     * @return list<string>
     */
    public function splitStatements(string $sql): array
    {
        return SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create())->splitStatements();
    }

    /**
     * Extract table name from INSERT statement.
     */
    public function extractInsertTable(string $sql): ?string
    {
        $sql = PostgreSqlLexicalMasker::maskComments($sql);
        if (preg_match('/INSERT\s+INTO\s+(?:ONLY\s+)?("[^"]+"|[a-zA-Z_]\w*(?:\."[^"]+"|\.(?:[a-zA-Z_]\w*))?)(?:\s+AS\s+"?(\w+)"?)?/i', $sql, $m) === 1) {
            return (new Parsing\Statement\Identifiers())->unquoteIdentifier((new Parsing\Statement\Identifiers())->stripSchemaPrefix($m[1]));
        }

        return null;
    }

    /**
     * Extract column list from INSERT statement.
     *
     * @return list<string>
     */
    public function extractInsertColumns(string $sql): array
    {
        $sql = PostgreSqlLexicalMasker::maskComments($sql);
        if (preg_match('/INSERT\s+INTO\s+(?:ONLY\s+)?(?:"[^"]+"|[a-zA-Z_]\w*(?:\."[^"]+"|\.(?:[a-zA-Z_]\w*))?)\s*\(([^)]+)\)\s*(?:VALUES|SELECT|DEFAULT)/i', $sql, $m) === 1) {
            return (new Parsing\Statement\Identifiers())->parseColumnList($m[1]);
        }

        return [];
    }

    /**
     * Extract VALUES rows from INSERT statement.
     *
     * @return list<list<string>>
     */
    public function extractInsertValues(string $sql): array
    {
        return (new Parsing\Statement\InsertSource())->extractInsertValues($sql);
    }

    /**
     * Check if INSERT has ON CONFLICT clause.
     */
    public function hasOnConflict(string $sql): bool
    {
        $sql = PostgreSqlLexicalMasker::maskComments($sql);
        return preg_match('/\bON\s+CONFLICT\b/i', $sql) === 1;
    }

    /**
     * Parses ON CONFLICT columns, predicates, or a named constraint.
     */
    public function extractOnConflictTarget(string $sql): ?PgSqlConflictTarget
    {
        return (new Parsing\Statement\ConflictClause())->extractOnConflictTarget($sql);
    }

    /**
     * Extract ON CONFLICT ... DO UPDATE SET columns and values.
     *
     * @return array{columns: list<string>, values: array<string, string>}
     */
    public function extractOnConflictUpdateColumns(string $sql): array
    {
        return (new Parsing\Statement\ConflictClause())->extractOnConflictUpdateColumns($sql);
    }

    /**
     * Returns the predicate limiting an ON CONFLICT update, when present.
     */
    public function extractOnConflictUpdateWhere(string $sql): ?string
    {
        return (new Parsing\Statement\ConflictClause())->extractOnConflictUpdateWhere($sql);
    }

    /**
     * Check if INSERT has a SELECT subquery (INSERT ... SELECT).
     */
    public function hasInsertSelect(string $sql): bool
    {
        return (new Parsing\Statement\InsertSource())->hasInsertSelect($sql);
    }

    /**
     * Extract the SELECT part from INSERT ... SELECT.
     */
    public function extractInsertSelectSql(string $sql): ?string
    {
        return (new Parsing\Statement\InsertSource())->extractInsertSelectSql($sql);
    }

    /**
     * Extract table name from UPDATE statement.
     */
    public function extractUpdateTable(string $sql): ?string
    {
        $sql = PostgreSqlLexicalMasker::maskComments($sql);
        if (preg_match('/UPDATE\s+(?:ONLY\s+)?("[^"]+"|[a-zA-Z_]\w*(?:\."[^"]+"|\.(?:[a-zA-Z_]\w*))?)(?:\s+(?:AS\s+)?("[^"]+"|[a-zA-Z_]\w*))?/i', $sql, $m) === 1) {
            return (new Parsing\Statement\Identifiers())->unquoteIdentifier((new Parsing\Statement\Identifiers())->stripSchemaPrefix($m[1]));
        }

        return null;
    }

    /**
     * Extract table alias from UPDATE statement.
     */
    public function extractUpdateAlias(string $sql): ?string
    {
        $sql = PostgreSqlLexicalMasker::maskComments($sql);
        if (preg_match('/UPDATE\s+(?:ONLY\s+)?(?:"[^"]+"|[a-zA-Z_]\w*(?:\."[^"]+"|\.(?:[a-zA-Z_]\w*))?)\s+(?:AS\s+)?("[^"]+"|[a-zA-Z_]\w*)\s+SET\b/i', $sql, $m) === 1) {
            return (new Parsing\Statement\Identifiers())->unquoteIdentifier($m[1]);
        }

        return null;
    }

    /**
     * Extract SET assignments from UPDATE statement.
     *
     * @return array<string, string> column => value expression
     */
    public function extractUpdateSets(string $sql): array
    {
        $setClause = SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create())->topLevelClause(
            ['SET'],
            [['FROM'], ['WHERE'], ['RETURNING']],
        );
        if ($setClause === null) {
            return [];
        }

        $assignments = SqlTokenStream::tokenize($setClause, PgSqlLexerProfile::create())->splitTopLevel();
        $result = [];

        foreach ($assignments as $assignment) {
            $assignment = trim($assignment);
            if (preg_match('/^("[^"]+"|[a-zA-Z_]\w*)\s*=\s*(.+)$/s', $assignment, $parts) === 1) {
                $colName = (new Parsing\Statement\Identifiers())->unquoteIdentifier($parts[1]);
                $result[$colName] = trim($parts[2]);
            }
        }

        return $result;
    }

    /**
     * Extract WHERE clause from UPDATE or DELETE statement.
     */
    public function extractWhereClause(string $sql): ?string
    {
        return SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create())->topLevelClause(
            ['WHERE'],
            [['RETURNING'], ['ORDER', 'BY'], ['LIMIT']],
        );
    }

    /**
     * Extract FROM clause from UPDATE statement (PostgreSQL extension).
     */
    public function extractUpdateFromClause(string $sql): ?string
    {
        return SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create())->topLevelClause(
            ['FROM'],
            [['WHERE'], ['RETURNING']],
        );
    }

    /**
     * Extract table name from DELETE statement.
     */
    public function extractDeleteTable(string $sql): ?string
    {
        $sql = PostgreSqlLexicalMasker::maskComments($sql);
        if (preg_match('/DELETE\s+FROM\s+(?:ONLY\s+)?("[^"]+"|[a-zA-Z_]\w*(?:\."[^"]+"|\.(?:[a-zA-Z_]\w*))?)(?:\s+(?:AS\s+)?("[^"]+"|[a-zA-Z_]\w*))?/i', $sql, $m) === 1) {
            return (new Parsing\Statement\Identifiers())->unquoteIdentifier((new Parsing\Statement\Identifiers())->stripSchemaPrefix($m[1]));
        }

        return null;
    }

    /**
     * Extract table alias from DELETE statement.
     */
    public function extractDeleteAlias(string $sql): ?string
    {
        $sql = PostgreSqlLexicalMasker::maskComments($sql);
        if (preg_match('/DELETE\s+FROM\s+(?:ONLY\s+)?(?:"[^"]+"|[a-zA-Z_]\w*(?:\."[^"]+"|\.(?:[a-zA-Z_]\w*))?)\s+(?:AS\s+)?("[^"]+"|[a-zA-Z_]\w*)\s+(?:USING\b|WHERE\b|RETURNING\b|$)/i', $sql, $m) === 1) {
            return (new Parsing\Statement\Identifiers())->unquoteIdentifier($m[1]);
        }

        return null;
    }

    /**
     * Extract USING clause from DELETE statement.
     */
    public function extractDeleteUsingClause(string $sql): ?string
    {
        return SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create())->topLevelClause(
            ['USING'],
            [['WHERE'], ['RETURNING']],
        );
    }

    /**
     * Extract table name from TRUNCATE statement.
     */
    public function extractTruncateTable(string $sql): ?string
    {
        return (new Parsing\Statement\TableDefinitionClauses())->extractTruncateTables($sql)[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function extractTruncateTables(string $sql): array
    {
        return (new Parsing\Statement\TableDefinitionClauses())->extractTruncateTables($sql);
    }

    /**
     * Extract table name from CREATE TABLE statement.
     */
    public function extractCreateTableName(string $sql): ?string
    {
        return (new Parsing\Statement\TableDefinitionClauses())->extractCreateTableName($sql);
    }

    /**
     * Check if CREATE TABLE has IF NOT EXISTS.
     */
    public function hasIfNotExists(string $sql): bool
    {
        return (new Parsing\Statement\TableDefinitionClauses())->hasIfNotExists($sql);
    }

    /**
     * Check if CREATE TABLE has AS SELECT.
     */
    public function hasCreateTableAsSelect(string $sql): bool
    {
        return (new Parsing\Statement\TableDefinitionClauses())->hasCreateTableAsSelect($sql);
    }

    /**
     * Extract the SELECT SQL from CREATE TABLE ... AS SELECT.
     */
    public function extractCreateTableSelectSql(string $sql): ?string
    {
        return (new Parsing\Statement\TableDefinitionClauses())->extractCreateTableSelectSql($sql);
    }

    /**
     * Check if CREATE TABLE has LIKE clause.
     */
    public function hasCreateTableLike(string $sql): bool
    {
        return (new Parsing\Statement\TableDefinitionClauses())->hasCreateTableLike($sql);
    }

    /**
     * Extract the LIKE source table name.
     */
    public function extractCreateTableLikeSource(string $sql): ?string
    {
        return (new Parsing\Statement\TableDefinitionClauses())->extractCreateTableLikeSource($sql);
    }

    /**
     * Extract table name from DROP TABLE statement.
     */
    public function extractDropTableName(string $sql): ?string
    {
        return (new Parsing\Statement\TableDefinitionClauses())->extractDropTableName($sql);
    }

    /**
     * Check if DROP TABLE has IF EXISTS.
     */
    public function hasDropTableIfExists(string $sql): bool
    {
        return (new Parsing\Statement\TableDefinitionClauses())->hasDropTableIfExists($sql);
    }

    /**
     * Extract table name from ALTER TABLE statement.
     */
    public function extractAlterTableName(string $sql): ?string
    {
        return (new Parsing\Statement\TableDefinitionClauses())->extractAlterTableName($sql);
    }

    /**
     * Unquote a PostgreSQL identifier (remove double quotes).
     */
    public function unquoteIdentifier(string $identifier): string
    {
        return (new Parsing\Statement\Identifiers())->unquoteIdentifier($identifier);
    }

    /**
     * Strip schema prefix from a potentially schema-qualified name.
     * "public"."users" -> "users", public.users -> users
     */
    public function stripSchemaPrefix(string $name): string
    {
        return (new Parsing\Statement\Identifiers())->stripSchemaPrefix($name);
    }

    /**
     * Extract table names referenced in a SELECT statement.
     *
     * @return list<string>
     */
    public function extractSelectTableNames(string $sql): array
    {
        return (new PgSqlSelectRelationParser())->tableNames($sql);
    }
}
