<?php

declare(strict_types=1);

namespace SqlFaker;

use Faker\Generator;
use Faker\Provider\Base;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Grammar;
use SqlFaker\MySql\GenerationPlans;
use SqlFaker\MySql\Grammar\MySqlGrammar;
use SqlFaker\MySql\StatementType;
use SqlFaker\Provider\SqlGeneratorFactory;

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
 * maxDepth selects shortest productions once the depth is reached; it is not a SQL length limit.
 * Generated statements may include whitespace and SQL comments. Optional clauses may be empty.
 * Seed the supplied Faker generator to reproduce a run within the same package version.
 *
 * @visibility public
 *
 * @example Register the provider with Faker
 *     $faker = \Faker\Factory::create();
 *     $faker->addProvider(new \SqlFaker\MySqlProvider($faker));
 *     $faker->integerLiteral(min: 42, max: 42) // => '42'
 */
final class MySqlProvider extends Base
{
    private Grammar $grammar;
    private SqlGenerator $sql;

    /**
     * Register SQL formatters on the supplied Faker generator.
     * @param Generator $generator Faker generator
     * @param string|null $version MySQL version tag (e.g., "mysql-8.4.7"). Null for default.
     *
     * @visibility public
     * @example Choose a supported database version
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker, 'mysql-5.7.44');
     *     $provider->integerLiteral(min: 42, max: 42) // => '42'
     * @example Reject an unsupported database version
     *     new \SqlFaker\MySqlProvider(\Faker\Factory::create(), 'missing-version') // throws \RuntimeException: Unsupported
     */
    public function __construct(Generator $generator, ?string $version = null)
    {
        parent::__construct($generator);

        $generator->addProvider($this);
        $resolvedVersion = MySqlGrammar::resolveVersion($version);
        $this->grammar = MySqlGrammar::load($resolvedVersion);
        $this->sql = SqlGeneratorFactory::forMySql($generator, $this->grammar, $resolvedVersion);
    }

    /**
     * Generate a syntactically valid SQL statement.
     *
     * @param StatementType|null $startRule Start rule (null for default)
     *
     * @visibility public
     * @example Select a statement type explicitly
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->sql(\SqlFaker\MySql\StatementType::Select, maxDepth: 0);
     *     preg_match('/\bSELECT\b/i', $sql) // => 1
     */
    public function sql(?StatementType $startRule = null, int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement($startRule?->value, $maxDepth));
    }

    /**
     * Generate SQL while requiring every MySQL row-value production to be non-empty.
     *
     * @visibility public
     * @example Select a statement type explicitly
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->sqlWithoutEmptyRows(\SqlFaker\MySql\StatementType::Select, maxDepth: 0);
     *     preg_match('/\bSELECT\b/i', $sql) // => 1
     */
    public function sqlWithoutEmptyRows(
        ?StatementType $startRule = null,
        int $maxDepth = PHP_INT_MAX,
    ): string {
        return $this->sql->generate(
            GenerationPlans::withoutEmptyRows($startRule?->value)->withMaxDepth($maxDepth),
        );
    }

    /**
     * Generate a SELECT statement.
     *
     * @visibility public
     * @example Generate select statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->selectStatement(maxDepth: 0);
     *     preg_match('/\bSELECT\b/is', $sql) // => 1
     */
    public function selectStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement(StatementType::Select->value, $maxDepth));
    }

    /**
     * Generate an INSERT statement.
     *
     * @visibility public
     * @example Generate insert statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->insertStatement(maxDepth: 0);
     *     preg_match('/\bINSERT\b/is', $sql) // => 1
     */
    public function insertStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement(StatementType::Insert->value, $maxDepth));
    }

    /**
     * Generate an UPDATE statement.
     *
     * @visibility public
     * @example Generate update statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->updateStatement(maxDepth: 0);
     *     preg_match('/\bUPDATE\b/is', $sql) // => 1
     */
    public function updateStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement(StatementType::Update->value, $maxDepth));
    }

    /**
     * Generate a DELETE statement.
     *
     * @visibility public
     * @example Generate delete statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->deleteStatement(maxDepth: 0);
     *     preg_match('/\bDELETE\b/is', $sql) // => 1
     */
    public function deleteStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement(StatementType::Delete->value, $maxDepth));
    }

    /**
     * Generate a LOAD DATA statement from MySQL's official load_stmt rule.
     *
     * @visibility public
     * @example Generate load data statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->loadDataStatement(maxDepth: 0);
     *     preg_match('/\bLOAD\b.*\bDATA\b.*\bINFILE\b/is', $sql) // => 1
     */
    public function loadDataStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::loadDataStatement()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a two-target UPDATE statement.
     *
     * @visibility public
     * @example Generate multi table update statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->multiTableUpdateStatement(maxDepth: 0);
     *     preg_match('/\bUPDATE\b.*,.*\bSET\b/is', $sql) // => 1
     */
    public function multiTableUpdateStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::multiTableUpdateStatement()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a two-target DELETE statement.
     *
     * @visibility public
     * @example Generate multi table delete statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->multiTableDeleteStatement(maxDepth: 0);
     *     preg_match('/\bDELETE\b.*,.*\bFROM\b/is', $sql) // => 1
     */
    public function multiTableDeleteStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::multiTableDeleteStatement()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a CREATE TABLE statement.
     *
     * @visibility public
     * @example Generate create table statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->createTableStatement(maxDepth: 0);
     *     preg_match('/\bCREATE\b.*\bTABLE\b/is', $sql) // => 1
     */
    public function createTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement(StatementType::CreateTable->value, $maxDepth));
    }

    /**
     * Generate an ALTER TABLE statement.
     *
     * @visibility public
     * @example Generate alter table statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->alterTableStatement(maxDepth: 0);
     *     preg_match('/\bALTER\b.*\bTABLE\b/is', $sql) // => 1
     */
    public function alterTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement(StatementType::AlterTable->value, $maxDepth));
    }

    /**
     * Generate a DROP TABLE statement.
     *
     * @visibility public
     * @example Generate drop table statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->dropTableStatement(maxDepth: 0);
     *     preg_match('/\bDROP\b.*\bTABLE\b/is', $sql) // => 1
     */
    public function dropTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement(StatementType::DropTable->value, $maxDepth));
    }

    /**
     * Generate any simple statement (SELECT, INSERT, UPDATE, DELETE, etc.).
     *
     * This is the most general method and can produce any type of SQL statement
     * that MySQL supports.
     *
     * @visibility public
     * @example Generate simple statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->simpleStatement(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function simpleStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement(StatementType::SimpleStatement->value, $maxDepth));
    }

    /**
     * Generate a MySQL identifier via grammar derivation.
     *
     * @visibility public
     * @example Generate identifier at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->identifier(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function identifier(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('ident', $maxDepth));
    }

    /**
     * Generate a backtick-quoted MySQL identifier.
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->quotedIdentifier(minLength: 4, maxLength: 4);
     *     preg_match('/^`[a-z_][a-z0-9_]{3}`$/', $token) // => 1
     */
    public function quotedIdentifier(int $minLength = 1, int $maxLength = 64): string
    {
        return $this->sql->generate(GenerationPlans::quotedIdentifier($minLength, $maxLength));
    }

    /**
     * Generate a MySQL string literal.
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->stringLiteral(minLength: 4, maxLength: 4);
     *     preg_match('/^\'[A-Za-z0-9_]{4}\'$/', $token) // => 1
     */
    public function stringLiteral(int $minLength = 1, int $maxLength = 255): string
    {
        return $this->sql->generate(GenerationPlans::stringLiteral($minLength, $maxLength));
    }

    /**
     * Generate a MySQL national string literal (N'...').
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->nationalStringLiteral(minLength: 4, maxLength: 4);
     *     preg_match('/^N\'[A-Za-z0-9_]{4}\'$/', $token) // => 1
     */
    public function nationalStringLiteral(int $minLength = 1, int $maxLength = 255): string
    {
        return $this->sql->generate(GenerationPlans::nationalStringLiteral($minLength, $maxLength));
    }

    /**
     * Generate a MySQL dollar-quoted string ($$...$$).
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->dollarQuotedString(minLength: 4, maxLength: 4);
     *     preg_match('/^\$\$[A-Za-z0-9_]{4}\$\$$/', $token) // => 1
     */
    public function dollarQuotedString(int $minLength = 1, int $maxLength = 255): string
    {
        return $this->sql->generate(GenerationPlans::dollarQuotedString($minLength, $maxLength));
    }

    /**
     * Generate a MySQL integer literal.
     *
     * @visibility public
     * @example Fix both bounds to obtain one value
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $provider->integerLiteral(min: 42, max: 42) // => '42'
     */
    public function integerLiteral(int $min = 1, int $max = 2147483647): string
    {
        return $this->sql->generate(GenerationPlans::integerLiteral($min, $max));
    }

    /**
     * Generate a MySQL long integer literal.
     *
     * @visibility public
     * @example Fix both bounds to obtain one value
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $provider->longIntegerLiteral(min: 42, max: 42) // => '42'
     */
    public function longIntegerLiteral(int $min = 0, int $max = 2147483647): string
    {
        return $this->sql->generate(GenerationPlans::longIntegerLiteral($min, $max));
    }

    /**
     * Generate a MySQL unsigned big integer literal.
     * Leading zeroes are removed after sampling minLength to maxLength digits.
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->unsignedBigIntLiteral(minLength: 4, maxLength: 4);
     *     preg_match('/^(0|[1-9][0-9]{0,3})$/', $token) // => 1
     */
    public function unsignedBigIntLiteral(int $minLength = 1, int $maxLength = 20): string
    {
        return $this->sql->generate(GenerationPlans::unsignedBigIntLiteral($minLength, $maxLength));
    }

    /**
     * Generate a MySQL decimal literal.
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->decimalLiteral(precision: 5, scale: 2);
     *     preg_match('/^[0-9]{1,3}\.[0-9]{2}$/', $token) // => 1
     */
    public function decimalLiteral(int $precision = 10, int $scale = 2): string
    {
        return $this->sql->generate(GenerationPlans::decimalLiteral($precision, $scale));
    }

    /**
     * Generate a MySQL float literal with exponent.
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->floatLiteral(precision: 5, scale: 2, minExponent: 3, maxExponent: 3);
     *     preg_match('/^[0-9]{1,3}\.[0-9]{2}e3$/', $token) // => 1
     */
    public function floatLiteral(int $precision = 10, int $scale = 2, int $minExponent = -38, int $maxExponent = 38): string
    {
        return $this->sql->generate(
            GenerationPlans::floatLiteral($precision, $scale, $minExponent, $maxExponent),
        );
    }

    /**
     * Generate a MySQL hexadecimal literal.
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->hexLiteral(minLength: 4, maxLength: 4);
     *     preg_match('/^0x[0-9a-f]{4}$/', $token) // => 1
     */
    public function hexLiteral(int $minLength = 1, int $maxLength = 16): string
    {
        return $this->sql->generate(GenerationPlans::hexLiteral($minLength, $maxLength));
    }

    /**
     * Generate a quoted MySQL hexadecimal literal.
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->quotedHexLiteral(minBytes: 2, maxBytes: 2);
     *     preg_match('/^X\'[0-9a-f]{4}\'$/', $token) // => 1
     */
    public function quotedHexLiteral(int $minBytes = 1, int $maxBytes = 8): string
    {
        return $this->sql->generate(GenerationPlans::quotedHexLiteral($minBytes, $maxBytes));
    }

    /**
     * Generate a MySQL binary literal.
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->binaryLiteral(minLength: 4, maxLength: 4);
     *     preg_match('/^0b[01]{4}$/', $token) // => 1
     */
    public function binaryLiteral(int $minLength = 1, int $maxLength = 64): string
    {
        return $this->sql->generate(GenerationPlans::binaryLiteral($minLength, $maxLength));
    }

    /**
     * Generate a MySQL hostname.
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->hostname(minParts: 2, maxParts: 2, maxPartLength: 4);
     *     preg_match('/^[a-z0-9]{1,4}\.[a-z0-9]{1,4}$/', $token) // => 1
     */
    public function hostname(int $minParts = 1, int $maxParts = 4, int $maxPartLength = 63): string
    {
        return $this->sql->generate(GenerationPlans::hostname($minParts, $maxParts, $maxPartLength));
    }

    /**
     * Generate a REPLACE statement.
     *
     * @visibility public
     * @example Generate replace statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->replaceStatement(maxDepth: 0);
     *     preg_match('/\bREPLACE\b/is', $sql) // => 1
     */
    public function replaceStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('replace_stmt', $maxDepth));
    }

    /**
     * Generate a TRUNCATE statement.
     *
     * @visibility public
     * @example Generate truncate statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->truncateStatement(maxDepth: 0);
     *     preg_match('/\bTRUNCATE\b/is', $sql) // => 1
     */
    public function truncateStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('truncate_stmt', $maxDepth));
    }

    /**
     * Generate a CREATE INDEX statement.
     *
     * @visibility public
     * @example Generate create index statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->createIndexStatement(maxDepth: 0);
     *     preg_match('/\bCREATE\b.*\bINDEX\b/is', $sql) // => 1
     */
    public function createIndexStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('create_index_stmt', $maxDepth));
    }

    /**
     * Generate a DROP INDEX statement.
     *
     * @visibility public
     * @example Generate drop index statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->dropIndexStatement(maxDepth: 0);
     *     preg_match('/\bDROP\b.*\bINDEX\b/is', $sql) // => 1
     */
    public function dropIndexStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('drop_index_stmt', $maxDepth));
    }

    /**
     * Generate a BEGIN statement.
     *
     * @visibility public
     * @example Generate begin statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->beginStatement(maxDepth: 0);
     *     preg_match('/\bBEGIN\b/is', $sql) // => 1
     */
    public function beginStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('begin_stmt', $maxDepth));
    }

    /**
     * Generate a COMMIT statement.
     *
     * @visibility public
     * @example Generate commit statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->commitStatement(maxDepth: 0);
     *     preg_match('/\bCOMMIT\b/is', $sql) // => 1
     */
    public function commitStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('commit', $maxDepth));
    }

    /**
     * Generate a ROLLBACK statement.
     *
     * @visibility public
     * @example Generate rollback statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->rollbackStatement(maxDepth: 0);
     *     preg_match('/\bROLLBACK\b/is', $sql) // => 1
     */
    public function rollbackStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('rollback', $maxDepth));
    }

    /**
     * Generate a MySQL expression.
     *
     * @visibility public
     * @example Generate expr at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->expr(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function expr(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('expr', $maxDepth));
    }

    /**
     * Generate a simple MySQL expression.
     *
     * @visibility public
     * @example Generate simple expr at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->simpleExpr(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function simpleExpr(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('simple_expr', $maxDepth));
    }

    /**
     * Generate a MySQL literal.
     *
     * @visibility public
     * @example Generate literal at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->literal(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function literal(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('literal', $maxDepth));
    }

    /**
     * Generate a MySQL predicate.
     *
     * @visibility public
     * @example Generate predicate at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->predicate(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function predicate(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('predicate', $maxDepth));
    }

    /**
     * Generate a WHERE clause.
     *
     * @visibility public
     * @example Generate where clause at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->whereClause(maxDepth: 0);
     *     preg_match('/\bWHERE\b/is', $sql) // => 1
     */
    public function whereClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('where_clause', $maxDepth));
    }

    /**
     * Generate an ORDER BY clause.
     *
     * @visibility public
     * @example Generate order clause at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->orderClause(maxDepth: 0);
     *     preg_match('/\bORDER\b.*\bBY\b/is', $sql) // => 1
     */
    public function orderClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('order_clause', $maxDepth));
    }

    /**
     * Generate a LIMIT clause.
     *
     * @visibility public
     * @example Generate limit clause at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->limitClause(maxDepth: 0);
     *     preg_match('/\bLIMIT\b/is', $sql) // => 1
     */
    public function limitClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('limit_clause', $maxDepth));
    }

    /**
     * Generate a table reference.
     *
     * @visibility public
     * @example Generate table reference at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->tableReference(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function tableReference(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('table_reference', $maxDepth));
    }

    /**
     * Generate a joined table.
     *
     * @visibility public
     * @example Generate joined table at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->joinedTable(maxDepth: 0);
     *     preg_match('/\bJOIN\b/is', $sql) // => 1
     */
    public function joinedTable(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('joined_table', $maxDepth));
    }

    /**
     * Generate a table identifier.
     *
     * @visibility public
     * @example Generate table ident at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->tableIdent(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function tableIdent(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('table_ident', $maxDepth));
    }

    /**
     * Generate a subquery.
     *
     * @visibility public
     * @example Generate subquery at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->subquery(maxDepth: 0);
     *     preg_match('/\(.*\bSELECT\b.*\)/is', $sql) // => 1
     */
    public function subquery(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('subquery', $maxDepth));
    }

    /**
     * Generate a WITH clause (CTE).
     *
     * @visibility public
     * @example Generate with clause at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->withClause(maxDepth: 0);
     *     preg_match('/\bWITH\b.*\bAS\b/is', $sql) // => 1
     */
    public function withClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::statement('with_clause', $maxDepth));
    }

    /**
     * Generate a named MySQL foreign-key table constraint.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate foreign key constraint at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->foreignKeyConstraint(maxDepth: 0);
     *     preg_match('/\bCONSTRAINT\b.*\bFOREIGN\b.*\bREFERENCES\b/is', $sql) // => 1
     */
    public function foreignKeyConstraint(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(
            GenerationPlans::foreignKeyConstraint($this->grammar)->withMaxDepth($maxDepth),
        );
    }

    /**
     * Generate an UPDATE whose joined source is a derived aggregate query.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate update join derived statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->updateJoinDerivedStatement(maxDepth: 0);
     *     preg_match('/\bUPDATE\b.*\bJOIN\b.*\bSELECT\b.*\bSET\b/is', $sql) // => 1
     */
    public function updateJoinDerivedStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(
            GenerationPlans::updateJoinDerivedStatement()->withMaxDepth($maxDepth),
        );
    }

    /**
     * Generate an INSERT whose source is a compound SELECT.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate insert select compound statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->insertSelectCompoundStatement(maxDepth: 0);
     *     preg_match('/\bINSERT\b.*\bSELECT\b.*\bUNION\b.*\bALL\b/is', $sql) // => 1
     */
    public function insertSelectCompoundStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(
            GenerationPlans::insertSelectCompoundStatement()->withMaxDepth($maxDepth),
        );
    }

    /**
     * Generate a MySQL row-alias upsert introduced in MySQL 8.0.19.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate insert row alias upsert statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->insertRowAliasUpsertStatement(maxDepth: 0);
     *     preg_match('/\bINSERT\b.*\bAS\b.*\bDUPLICATE\b.*\bUPDATE\b/is', $sql) // => 1
     */
    public function insertRowAliasUpsertStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(
            GenerationPlans::insertRowAliasUpsertStatement()->withMaxDepth($maxDepth),
        );
    }
    /**
     * Generate an upsert whose update expression calls a function.
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate insert function upsert statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->insertFunctionUpsertStatement(maxDepth: 0);
     *     preg_match('/\bINSERT\b.*\bDUPLICATE\b.*\bUPDATE\b.*\bIF\b/is', $sql) // => 1
     */
    public function insertFunctionUpsertStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(
            GenerationPlans::insertFunctionUpsertStatement()->withMaxDepth($maxDepth),
        );
    }
    /**
     * Generate a query using the dialect full-text search syntax.
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate full text search statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->fullTextSearchStatement(maxDepth: 0);
     *     preg_match('/\bSELECT\b.*\bMATCH\b.*\bAGAINST\b/is', $sql) // => 1
     */
    public function fullTextSearchStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::fullTextSearchStatement()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a temporary-table declaration.
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate temporary table statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->temporaryTableStatement(maxDepth: 0);
     *     preg_match('/\bCREATE\b.*\bTEMP(?:ORARY)?\b.*\bTABLE\b/is', $sql) // => 1
     */
    public function temporaryTableStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(
            GenerationPlans::temporaryTableStatement()->withMaxDepth($maxDepth),
        );
    }
    /**
     * Generate a view declaration.
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate view statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->viewStatement(maxDepth: 0);
     *     preg_match('/\bCREATE\b.*\bVIEW\b.*\bAS\b/is', $sql) // => 1
     */
    public function viewStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::viewStatement()->withMaxDepth($maxDepth));
    }
    /**
     * Generate a table with a generated column.
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate generated column statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->generatedColumnStatement(maxDepth: 0);
     *     preg_match('/\bGENERATED\b.*\bALWAYS\b.*\bAS\b/is', $sql) // => 1
     */
    public function generatedColumnStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::generatedColumnStatement()->withMaxDepth($maxDepth));
    }
    /**
     * Generate a foreign key with cascading update and delete actions.
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate foreign key cascade statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->foreignKeyCascadeStatement(maxDepth: 0);
     *     preg_match('/\bREFERENCES\b.*\bCASCADE\b.*\bCASCADE\b/is', $sql) // => 1
     */
    public function foreignKeyCascadeStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::foreignKeyCascadeStatement()->withMaxDepth($maxDepth));
    }
    /**
     * Generate a SELECT restricted to a named partition.
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate partition select statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\MySqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->partitionSelectStatement(maxDepth: 0);
     *     preg_match('/\bSELECT\b.*\bPARTITION\b/is', $sql) // => 1
     */
    public function partitionSelectStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::partitionSelectStatement()->withMaxDepth($maxDepth));
    }
}
