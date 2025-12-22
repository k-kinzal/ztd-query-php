<?php

declare(strict_types=1);

namespace SqlFaker;

use Faker\Generator;
use Faker\Provider\Base;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\SqlGenerator;
use SqlFaker\MySql\StatementType;

/**
 * Faker Provider for generating syntactically valid MySQL SQL statements.
 *
 * This provider uses MySQL's official Bison grammar (sql_yacc.yy) to generate
 * SQL that is syntactically valid. Note that generated SQL may not be semantically
 * valid (tables/columns may not exist).
 *
 * Supported MySQL versions:
 *   - mysql-5.6.51
 *   - mysql-5.7.44
 *   - mysql-8.0.44
 *   - mysql-8.1.0
 *   - mysql-8.2.0
 *   - mysql-8.3.0
 *   - mysql-8.4.7 (default)
 *   - mysql-9.0.1
 *   - mysql-9.1.0
 *
 * Usage:
 *   $faker = \Faker\Factory::create();
 *   $faker->addProvider(new \SqlFaker\MySqlProvider($faker));
 *
 *   // Use specific MySQL version
 *   $faker->addProvider(new \SqlFaker\MySqlProvider($faker, 'mysql-5.7.44'));
 *
 *   // Generic SQL
 *   $faker->sql();
 *
 *   // Specific statement types
 *   $faker->selectStatement();
 *   $faker->insertStatement();
 *   $faker->updateStatement();
 *   $faker->deleteStatement();
 *
 *   // With start rule and maxDepth
 *   $faker->sql(StatementType::Select);
 *   $faker->sql(StatementType::Insert, maxDepth: 6);
 */
final class MySqlProvider extends Base
{
    private SqlGenerator $sql;

    /**
     * @param Generator $generator Faker generator
     * @param string|null $version MySQL version tag (e.g., "mysql-8.4.7"). Null for default.
     */
    public function __construct(Generator $generator, ?string $version = null)
    {
        parent::__construct($generator);

        // Register this provider with Faker so that SqlGenerator can use
        // Terminal generators (identifier, stringLiteral, etc.) via $faker->identifier()
        $generator->addProvider($this);

        $this->sql = new SqlGenerator(Grammar::load($version), $generator);
    }

    /**
     * Generate a syntactically valid SQL statement.
     *
     * @param StatementType|null $startRule Start rule (null for default)
     * @param int $maxDepth Maximum recursion depth (PHP_INT_MAX = unlimited)
     * @return string Generated SQL statement
     *
     * @example $faker->sql() // Any valid MySQL statement
     * @example $faker->sql(StatementType::Select) // Generates a SELECT statement
     * @example $faker->sql(StatementType::Insert, maxDepth: 6) // Generates simpler INSERT
     */
    public function sql(?StatementType $startRule = null, int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate($startRule !== null ? $startRule->value : 'simple_statement_or_begin', $maxDepth);
    }

    /**
     * Generate a SELECT statement.
     *
     * @param int $maxDepth Maximum recursion depth (PHP_INT_MAX = unlimited)
     * @return string Generated SELECT statement
     *
     * @example $faker->selectStatement() // "SELECT id, name FROM users WHERE status = 1"
     * @example $faker->selectStatement(maxDepth: 6) // Generates simpler SELECT
     */
    public function selectStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(StatementType::Select->value, $maxDepth);
    }

    /**
     * Generate an INSERT statement.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler SQL)
     * @return string Generated INSERT statement
     *
     * @example $faker->insertStatement() // "INSERT INTO users (name, email) VALUES ('foo', 'bar')"
     * @example $faker->insertStatement(maxDepth: 6) // Generates simpler INSERT
     */
    public function insertStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(StatementType::Insert->value, $maxDepth);
    }

    /**
     * Generate an UPDATE statement.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler SQL)
     * @return string Generated UPDATE statement
     *
     * @example $faker->updateStatement() // "UPDATE users SET status = 0 WHERE id = 5"
     * @example $faker->updateStatement(maxDepth: 6) // Generates simpler UPDATE
     */
    public function updateStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(StatementType::Update->value, $maxDepth);
    }

    /**
     * Generate a DELETE statement.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler SQL)
     * @return string Generated DELETE statement
     *
     * @example $faker->deleteStatement() // "DELETE FROM users WHERE id = 5"
     * @example $faker->deleteStatement(maxDepth: 6) // Generates simpler DELETE
     */
    public function deleteStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(StatementType::Delete->value, $maxDepth);
    }

    /**
     * Generate a CREATE TABLE statement.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler SQL)
     * @return string Generated CREATE TABLE statement
     *
     * @example $faker->createTableStatement() // "CREATE TABLE t1 (id INT PRIMARY KEY)"
     */
    public function createTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(StatementType::CreateTable->value, $maxDepth);
    }

    /**
     * Generate an ALTER TABLE statement.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler SQL)
     * @return string Generated ALTER TABLE statement
     *
     * @example $faker->alterTableStatement() // "ALTER TABLE t1 ADD COLUMN name VARCHAR(255)"
     */
    public function alterTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(StatementType::AlterTable->value, $maxDepth);
    }

    /**
     * Generate a DROP TABLE statement.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler SQL)
     * @return string Generated DROP TABLE statement
     *
     * @example $faker->dropTableStatement() // "DROP TABLE IF EXISTS t1"
     */
    public function dropTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(StatementType::DropTable->value, $maxDepth);
    }

    /**
     * Generate any simple statement (SELECT, INSERT, UPDATE, DELETE, etc.).
     *
     * This is the most general method and can produce any type of SQL statement
     * that MySQL supports.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler SQL)
     * @return string Generated SQL statement
     *
     * @example $faker->simpleStatement() // Any valid MySQL statement
     */
    public function simpleStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(StatementType::SimpleStatement->value, $maxDepth);
    }

    // =========================================================================
    // Terminal (Lexical) Generators
    // =========================================================================

    /**
     * Generate a MySQL identifier.
     *
     * @return string Generated identifier (e.g., "t1", "col42", "tbl123")
     *
     * @example $faker->identifier() // "t42"
     */
    public function identifier(): string
    {
        $prefixes = ['t', 'tbl', 'col', 'db', 'u', 'idx', 'tmp', 'x', 'y', 'z'];
        /** @var string $prefix */
        $prefix = $this->generator->randomElement($prefixes);
        $suffix = $this->generator->numberBetween(1, 1000000);

        return $prefix . $suffix;
    }

    /**
     * Generate a backtick-quoted MySQL identifier.
     *
     * @return string Generated quoted identifier (e.g., "`t1`", "`col42`")
     *
     * @example $faker->quotedIdentifier() // "`t42`"
     */
    public function quotedIdentifier(): string
    {
        return '`' . $this->identifier() . '`';
    }

    /**
     * Generate a MySQL string literal.
     *
     * @return string Generated string literal (e.g., "'abc123'")
     *
     * @example $faker->stringLiteral() // "'hello'"
     */
    public function stringLiteral(): string
    {
        $len = $this->generator->numberBetween(1, 24);
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_';
        $max = strlen($alphabet) - 1;

        $buf = '';
        for ($i = 0; $i < $len; $i++) {
            $buf .= $alphabet[$this->generator->numberBetween(0, $max)];
        }

        return "'" . $buf . "'";
    }

    /**
     * Generate a MySQL national string literal (N'...').
     *
     * @return string Generated national string literal (e.g., "N'abc'")
     *
     * @example $faker->nationalStringLiteral() // "N'hello'"
     */
    public function nationalStringLiteral(): string
    {
        return 'N' . $this->stringLiteral();
    }

    /**
     * Generate a MySQL dollar-quoted string ($$...$$).
     *
     * @return string Generated dollar-quoted string (e.g., "$$abc$$")
     *
     * @example $faker->dollarQuotedString() // "$$hello$$"
     */
    public function dollarQuotedString(): string
    {
        $len = $this->generator->numberBetween(1, 24);
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_';
        $max = strlen($alphabet) - 1;

        $buf = '';
        for ($i = 0; $i < $len; $i++) {
            $buf .= $alphabet[$this->generator->numberBetween(0, $max)];
        }

        return '$$' . $buf . '$$';
    }

    /**
     * Generate a MySQL integer literal.
     *
     * @return string Generated integer literal (e.g., "42", "999")
     *
     * @example $faker->integerLiteral() // "123"
     */
    public function integerLiteral(): string
    {
        return (string) $this->generator->numberBetween(0, 1000);
    }

    /**
     * Generate a MySQL long integer literal.
     *
     * @return string Generated long integer literal
     *
     * @example $faker->longIntegerLiteral() // "1234567890"
     */
    public function longIntegerLiteral(): string
    {
        return (string) $this->generator->numberBetween(0, 2147483647);
    }

    /**
     * Generate a MySQL unsigned big integer literal.
     *
     * @return string Generated unsigned big integer literal
     *
     * @example $faker->unsignedBigIntLiteral() // "12345678901234567890"
     */
    public function unsignedBigIntLiteral(): string
    {
        $maxDigits = 20;
        $len = $this->generator->numberBetween(1, $maxDigits);

        $buf = '';
        for ($i = 0; $i < $len; $i++) {
            $buf .= (string) $this->generator->numberBetween(0, 9);
        }

        $trimmed = ltrim($buf, '0');

        return $trimmed === '' ? '0' : $trimmed;
    }

    /**
     * Generate a MySQL decimal literal.
     *
     * @return string Generated decimal literal (e.g., "123.45")
     *
     * @example $faker->decimalLiteral() // "99.50"
     */
    public function decimalLiteral(): string
    {
        $intPart = $this->generator->numberBetween(0, 1000000);
        $fracPart = $this->generator->numberBetween(0, 999999);

        return $intPart . '.' . str_pad((string) $fracPart, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a MySQL float literal with exponent.
     *
     * @return string Generated float literal (e.g., "1.23e10")
     *
     * @example $faker->floatLiteral() // "3.14e-5"
     */
    public function floatLiteral(): string
    {
        $mantissa = $this->decimalLiteral();
        $exp = $this->generator->numberBetween(-20, 20);

        return $mantissa . 'e' . $exp;
    }

    /**
     * Generate a MySQL hexadecimal literal.
     *
     * @return string Generated hex literal (e.g., "0x1a2b3c")
     *
     * @example $faker->hexLiteral() // "0xdeadbeef"
     */
    public function hexLiteral(): string
    {
        $len = $this->generator->numberBetween(1, 16);
        $alphabet = '0123456789abcdef';
        $max = strlen($alphabet) - 1;

        $buf = '';
        for ($i = 0; $i < $len; $i++) {
            $buf .= $alphabet[$this->generator->numberBetween(0, $max)];
        }

        return '0x' . $buf;
    }

    /**
     * Generate a MySQL binary literal.
     *
     * @return string Generated binary literal (e.g., "0b1010")
     *
     * @example $faker->binaryLiteral() // "0b11001010"
     */
    public function binaryLiteral(): string
    {
        $len = $this->generator->numberBetween(1, 32);
        $buf = '';
        for ($i = 0; $i < $len; $i++) {
            $buf .= (string) $this->generator->numberBetween(0, 1);
        }

        return '0b' . $buf;
    }

    /**
     * Generate a MySQL hostname.
     *
     * @return string Generated hostname (e.g., "localhost", "host1")
     *
     * @example $faker->hostname() // "localhost"
     */
    public function hostname(): string
    {
        $hosts = ['localhost', 'host1', 'host2', 'db_local', 'example_com'];
        /** @var string $host */
        $host = $this->generator->randomElement($hosts);

        return $host;
    }

    // =========================================================================
    // Non-Terminal (Grammar Rule) Generators
    // =========================================================================

    /**
     * Generate a REPLACE statement.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler SQL)
     * @return string Generated REPLACE statement
     *
     * @example $faker->replaceStatement() // "REPLACE INTO t1 (col1) VALUES (1)"
     */
    public function replaceStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('replace_stmt', $maxDepth);
    }

    /**
     * Generate a TRUNCATE statement.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler SQL)
     * @return string Generated TRUNCATE statement
     *
     * @example $faker->truncateStatement() // "TRUNCATE TABLE t1"
     */
    public function truncateStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('truncate_stmt', $maxDepth);
    }

    /**
     * Generate a CREATE INDEX statement.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler SQL)
     * @return string Generated CREATE INDEX statement
     *
     * @example $faker->createIndexStatement() // "CREATE INDEX idx1 ON t1 (col1)"
     */
    public function createIndexStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('create_index_stmt', $maxDepth);
    }

    /**
     * Generate a DROP INDEX statement.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler SQL)
     * @return string Generated DROP INDEX statement
     *
     * @example $faker->dropIndexStatement() // "DROP INDEX idx1 ON t1"
     */
    public function dropIndexStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('drop_index_stmt', $maxDepth);
    }

    /**
     * Generate a BEGIN statement.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler SQL)
     * @return string Generated BEGIN statement
     *
     * @example $faker->beginStatement() // "BEGIN"
     */
    public function beginStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('begin_stmt', $maxDepth);
    }

    /**
     * Generate a COMMIT statement.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler SQL)
     * @return string Generated COMMIT statement
     *
     * @example $faker->commitStatement() // "COMMIT"
     */
    public function commitStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('commit', $maxDepth);
    }

    /**
     * Generate a ROLLBACK statement.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler SQL)
     * @return string Generated ROLLBACK statement
     *
     * @example $faker->rollbackStatement() // "ROLLBACK"
     */
    public function rollbackStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('rollback', $maxDepth);
    }

    /**
     * Generate a MySQL expression.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler expression)
     * @return string Generated expression
     *
     * @example $faker->expr() // "col1 + 1"
     */
    public function expr(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('expr', $maxDepth);
    }

    /**
     * Generate a simple MySQL expression.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler expression)
     * @return string Generated simple expression
     *
     * @example $faker->simpleExpr() // "col1"
     */
    public function simpleExpr(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('simple_expr', $maxDepth);
    }

    /**
     * Generate a MySQL literal.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler literal)
     * @return string Generated literal
     *
     * @example $faker->literal() // "'hello'" or "123"
     */
    public function literal(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('literal', $maxDepth);
    }

    /**
     * Generate a MySQL predicate.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler predicate)
     * @return string Generated predicate
     *
     * @example $faker->predicate() // "col1 = 1"
     */
    public function predicate(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('predicate', $maxDepth);
    }

    /**
     * Generate a WHERE clause.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler clause)
     * @return string Generated WHERE clause
     *
     * @example $faker->whereClause() // "WHERE col1 = 1"
     */
    public function whereClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('where_clause', $maxDepth);
    }

    /**
     * Generate an ORDER BY clause.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler clause)
     * @return string Generated ORDER BY clause
     *
     * @example $faker->orderClause() // "ORDER BY col1 ASC"
     */
    public function orderClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('order_clause', $maxDepth);
    }

    /**
     * Generate a LIMIT clause.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler clause)
     * @return string Generated LIMIT clause
     *
     * @example $faker->limitClause() // "LIMIT 10"
     */
    public function limitClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('limit_clause', $maxDepth);
    }

    /**
     * Generate a table reference.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler reference)
     * @return string Generated table reference
     *
     * @example $faker->tableReference() // "t1 AS a"
     */
    public function tableReference(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('table_reference', $maxDepth);
    }

    /**
     * Generate a joined table.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler join)
     * @return string Generated joined table
     *
     * @example $faker->joinedTable() // "t1 JOIN t2 ON t1.id = t2.id"
     */
    public function joinedTable(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('joined_table', $maxDepth);
    }

    /**
     * Generate a table identifier.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler identifier)
     * @return string Generated table identifier
     *
     * @example $faker->tableIdent() // "db1.t1" or "t1"
     */
    public function tableIdent(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('table_ident', $maxDepth);
    }

    /**
     * Generate a subquery.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler subquery)
     * @return string Generated subquery
     *
     * @example $faker->subquery() // "(SELECT * FROM t1)"
     */
    public function subquery(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('subquery', $maxDepth);
    }

    /**
     * Generate a WITH clause (CTE).
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler CTE)
     * @return string Generated WITH clause
     *
     * @example $faker->withClause() // "WITH cte AS (SELECT 1)"
     */
    public function withClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('with_clause', $maxDepth);
    }
}
