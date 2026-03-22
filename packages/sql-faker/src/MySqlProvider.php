<?php

declare(strict_types=1);

namespace SqlFaker;

use Faker\Generator;
use Faker\Provider\Base;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Generation\FakerRandomSource;
use SqlFaker\MySql\LexicalValueGenerator;
use SqlFaker\MySql\LexicalValueSource;
use SqlFaker\MySql\StatementGenerator as MySqlStatementGenerator;
use SqlFaker\MySql\StatementType;

/**
 * Faker Provider for generating MySQL SQL from the documented supported language.
 *
 * This provider compiles a constrained grammar from MySQL's upstream grammar
 * snapshot and generates SQL through the runtime algorithm contract. Generated SQL
 * is syntax-oriented fuzzing input, not a guarantee of semantic validity against a
 * live schema.
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
final class MySqlProvider extends Base implements LexicalValueSource
{
    private LexicalValueGenerator $lexicalValues;
    private MySqlStatementGenerator $statementGenerator;

    /**
     * @param Generator $generator Faker generator
     * @param string|null $version MySQL version tag (e.g., "mysql-8.4.7"). Null for default.
     */
    public function __construct(Generator $generator, ?string $version = null)
    {
        parent::__construct($generator);

        $generator->addProvider($this);

        $this->lexicalValues = new LexicalValueGenerator(new FakerRandomSource($generator));
        $this->statementGenerator = new MySqlStatementGenerator($generator, $version, $this->lexicalValues);
    }

    public function generate(GenerationRequest $request): string
    {
        if ($request->seed === null) {
            $request = new GenerationRequest(
                startRule: $request->startRule,
                seed: $this->generator->numberBetween(1, 2_147_483_647),
                maxDepth: $request->maxDepth,
            );
        }

        return $this->statementGenerator->generate($request);
    }

    /**
     * Generate a MySQL SQL statement from the supported-language contract.
     *
     * @param StatementType|null $startRule Start rule (null uses the default `simple_statement_or_begin` entry rule)
     * @param int $maxDepth Maximum recursion depth (PHP_INT_MAX = unlimited)
     * @return string Generated SQL statement
     *
     * @example $faker->sql() // Any valid MySQL statement
     * @example $faker->sql(StatementType::Select) // Generates a SELECT statement
     * @example $faker->sql(StatementType::Insert, maxDepth: 6) // Generates simpler INSERT
     */
    public function sql(?StatementType $startRule = null, int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(
            startRule: $startRule !== null ? $startRule->value : 'simple_statement_or_begin',
            maxDepth: $maxDepth,
        ));
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
        return $this->generate(new GenerationRequest(startRule: StatementType::Select->value, maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: StatementType::Insert->value, maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: StatementType::Update->value, maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: StatementType::Delete->value, maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: StatementType::CreateTable->value, maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: StatementType::AlterTable->value, maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: StatementType::DropTable->value, maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: StatementType::SimpleStatement->value, maxDepth: $maxDepth));
    }

    /**
     * Generate a MySQL identifier via grammar derivation.
     *
     * @param int $maxDepth Maximum recursion depth
     * @return string Generated identifier (e.g., "t1", "col42", "COMMIT")
     *
     * @example $faker->identifier() // "abc"
     */
    public function identifier(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'ident', maxDepth: $maxDepth));
    }

    /**
     * Generate a backtick-quoted MySQL identifier.
     *
     * @return string Generated quoted identifier (e.g., "`abc`", "`x1`")
     *
     * @example $faker->quotedIdentifier() // "`abc`"
     */
    public function quotedIdentifier(int $minLength = 1, int $maxLength = 64): string
    {
        return $this->lexicalValues->quotedIdentifier($minLength, $maxLength);
    }

    /**
     * Generate a MySQL string literal.
     *
     * @return string Generated string literal (e.g., "'abc123'")
     *
     * @example $faker->stringLiteral() // "'hello'"
     */
    public function stringLiteral(int $minLength = 1, int $maxLength = 32): string
    {
        return $this->lexicalValues->stringLiteral($minLength, $maxLength);
    }

    /**
     * Generate a MySQL national string literal (N'...').
     *
     * @return string Generated national string literal (e.g., "N'abc'")
     *
     * @example $faker->nationalStringLiteral() // "N'hello'"
     */
    public function nationalStringLiteral(int $minLength = 1, int $maxLength = 32): string
    {
        return $this->lexicalValues->nationalStringLiteral($minLength, $maxLength);
    }

    /**
     * Generate a MySQL dollar-quoted string ($$...$$).
     *
     * @return string Generated dollar-quoted string (e.g., "$$abc$$")
     *
     * @example $faker->dollarQuotedString() // "$$hello$$"
     */
    public function dollarQuotedString(int $minLength = 1, int $maxLength = 32): string
    {
        return $this->lexicalValues->dollarQuotedString($minLength, $maxLength);
    }

    /**
     * Generate a MySQL integer literal.
     *
     * @return string Generated integer literal (e.g., "42", "9876543210")
     *
     * @example $faker->integerLiteral() // "123"
     */
    public function integerLiteral(int $min = 1, int $max = 2147483647): string
    {
        return $this->lexicalValues->integerLiteral($min, $max);
    }

    /**
     * Generate a MySQL long integer literal.
     *
     * @return string Generated long integer literal
     *
     * @example $faker->longIntegerLiteral() // "1234567890"
     */
    public function longIntegerLiteral(int $min = 0, int $max = 2147483647): string
    {
        return $this->lexicalValues->longIntegerLiteral($min, $max);
    }

    /**
     * Generate a MySQL unsigned big integer literal.
     *
     * @return string Generated unsigned big integer literal
     *
     * @example $faker->unsignedBigIntLiteral() // "12345678901234567890"
     */
    public function unsignedBigIntLiteral(int $minLength = 1, int $maxLength = 20): string
    {
        return $this->lexicalValues->unsignedBigIntLiteral($minLength, $maxLength);
    }

    /**
     * Generate a MySQL decimal literal.
     *
     * @return string Generated decimal literal (e.g., "123.45")
     *
     * @example $faker->decimalLiteral() // "99.50"
     */
    public function decimalLiteral(int $precision = 10, int $scale = 2): string
    {
        return $this->lexicalValues->decimalLiteral($precision, $scale);
    }

    /**
     * Generate a MySQL float literal with exponent.
     *
     * @return string Generated float literal (e.g., "1.23e10")
     *
     * @example $faker->floatLiteral() // "3.14e-5"
     */
    public function floatLiteral(int $precision = 10, int $scale = 2, int $minExponent = -38, int $maxExponent = 38): string
    {
        return $this->lexicalValues->floatLiteral($precision, $scale, $minExponent, $maxExponent);
    }

    /**
     * Generate a MySQL hexadecimal literal.
     *
     * @return string Generated hex literal (e.g., "0x1a2b3c")
     *
     * @example $faker->hexLiteral() // "0xdeadbeef"
     */
    public function hexLiteral(int $minLength = 1, int $maxLength = 16): string
    {
        return $this->lexicalValues->hexLiteral($minLength, $maxLength);
    }

    /**
     * Generate a MySQL binary literal.
     *
     * @return string Generated binary literal (e.g., "0b1010")
     *
     * @example $faker->binaryLiteral() // "0b11001010"
     */
    public function binaryLiteral(int $minLength = 1, int $maxLength = 64): string
    {
        return $this->lexicalValues->binaryLiteral($minLength, $maxLength);
    }

    /**
     * Generate a MySQL hostname.
     *
     * @return string Generated hostname (e.g., "a1b2.c3d4", "x")
     *
     * @example $faker->hostname() // "abc.def"
     */
    public function hostname(int $minParts = 1, int $maxParts = 1, int $maxPartLength = 16): string
    {
        return $this->lexicalValues->hostname($minParts, $maxParts, $maxPartLength);
    }

    /**
     * Generate a replication filter wildcard pattern in db.table form.
     */
    public function filterWildcardPattern(int $maxPartLength = 12): string
    {
        return $this->lexicalValues->filterWildcardPattern($maxPartLength);
    }

    /**
     * Generate a valid RESET MASTER TO index.
     */
    public function resetMasterIndex(): string
    {
        return $this->lexicalValues->resetMasterIndex();
    }

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
        return $this->generate(new GenerationRequest(startRule: 'replace_stmt', maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: 'truncate_stmt', maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: 'create_index_stmt', maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: 'drop_index_stmt', maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: 'begin_stmt', maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: 'commit', maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: 'rollback', maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: 'expr', maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: 'simple_expr', maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: 'literal', maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: 'predicate', maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: 'where_clause', maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: 'order_clause', maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: 'limit_clause', maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: 'table_reference', maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: 'joined_table', maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: 'table_ident', maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: 'subquery', maxDepth: $maxDepth));
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
        return $this->generate(new GenerationRequest(startRule: 'with_clause', maxDepth: $maxDepth));
    }

}
