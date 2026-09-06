<?php

declare(strict_types=1);

namespace SqlFaker;

use Faker\Generator;
use Faker\Provider\Base;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\PostgreSql\GenerationPlans;
use SqlFaker\PostgreSql\Grammar\PgGrammar;
use SqlFaker\PostgreSql\StatementType;
use SqlFaker\Provider\SqlGeneratorFactory;

/**
 * Faker Provider for generating syntactically valid PostgreSQL SQL statements.
 *
 * This provider uses PostgreSQL's official Bison grammar (gram.y) to generate
 * SQL that is syntactically valid. Note that generated SQL may not be semantically
 * valid (tables/columns may not exist).
 *
 * maxDepth selects shortest productions once the depth is reached; it is not a SQL length limit.
 * Generated statements may include whitespace and SQL comments. Optional clauses may be empty.
 * Seed the supplied Faker generator to reproduce a run within the same package version.
 *
 * @visibility public
 *
 * @example Register the provider with Faker
 *     $faker = \Faker\Factory::create();
 *     $faker->addProvider(new \SqlFaker\PostgreSqlProvider($faker));
 *     $faker->parameterMarker(min: 2, max: 2) // => '$2'
 */
final class PostgreSqlProvider extends Base
{
    private SqlGenerator $sql;

    /**
     * Register SQL formatters on the supplied Faker generator.
     * @param Generator $generator Faker generator
     * @param string|null $version PostgreSQL version tag. Null for default.
     *
     * @visibility public
     * @example Choose a supported database version
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker, 'pg-17.2');
     *     $provider->integerLiteral(min: 42, max: 42) // => '42'
     * @example Reject an unsupported database version
     *     new \SqlFaker\PostgreSqlProvider(\Faker\Factory::create(), 'missing-version') // throws \RuntimeException: Unsupported
     */
    public function __construct(Generator $generator, ?string $version = null)
    {
        parent::__construct($generator);

        $generator->addProvider($this);
        $resolvedVersion = PgGrammar::resolveVersion($version);
        $this->sql = SqlGeneratorFactory::forPostgreSql($generator, PgGrammar::load($resolvedVersion), $resolvedVersion);
    }

    /**
     * Generate a syntactically valid PostgreSQL SQL statement.
     *
     * @param StatementType|null $type Statement type (null for random)
     * @return non-empty-string Generated SQL statement
     *
     * @visibility public
     * @example Select a statement type explicitly
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->sql(\SqlFaker\PostgreSql\StatementType::Select, maxDepth: 0);
     *     preg_match('/\bSELECT\b/i', $sql) // => 1
     */
    public function sql(?StatementType $type = null, int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statementOfType($this->generator, $type, $maxDepth));
    }

    /**
     * Generate a PostgreSQL SELECT statement.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate select statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->selectStatement(maxDepth: 0);
     *     preg_match('/\bSELECT\b/is', $sql) // => 1
     */
    public function selectStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule(StatementType::Select->value)->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL INSERT statement.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate insert statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->insertStatement(maxDepth: 0);
     *     preg_match('/\bINSERT\b/is', $sql) // => 1
     */
    public function insertStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule(StatementType::Insert->value)->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL UPDATE statement.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate update statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->updateStatement(maxDepth: 0);
     *     preg_match('/\bUPDATE\b/is', $sql) // => 1
     */
    public function updateStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule(StatementType::Update->value)->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL DELETE statement.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate delete statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->deleteStatement(maxDepth: 0);
     *     preg_match('/\bDELETE\b/is', $sql) // => 1
     */
    public function deleteStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule(StatementType::Delete->value)->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL CREATE TABLE statement.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate create table statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->createTableStatement(maxDepth: 0);
     *     preg_match('/\bCREATE\b.*\bTABLE\b/is', $sql) // => 1
     */
    public function createTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule(StatementType::CreateTable->value)->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL CREATE TABLE AS statement.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate create table as statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->createTableAsStatement(maxDepth: 0);
     *     preg_match('/\bCREATE\b.*\bTABLE\b.*\bAS\b/is', $sql) // => 1
     */
    public function createTableAsStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule(StatementType::CreateTableAs->value)->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL CREATE DOMAIN statement.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate create domain statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->createDomainStatement(maxDepth: 0);
     *     preg_match('/\bCREATE\b.*\bDOMAIN\b/is', $sql) // => 1
     */
    public function createDomainStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(
            GenerationPlan::fromRule(StatementType::CreateDomain->value)->requiringNonEmpty()->withMaxDepth($maxDepth),
        );
    }

    /**
     * Generate a PostgreSQL ALTER TABLE statement.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate alter table statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->alterTableStatement(maxDepth: 0);
     *     preg_match('/\bALTER\b.*\bTABLE\b/is', $sql) // => 1
     */
    public function alterTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule(StatementType::AlterTable->value)->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL DROP TABLE statement.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate drop table statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->dropTableStatement(maxDepth: 0);
     *     preg_match('/\bDROP\b.*\bTABLE\b/is', $sql) // => 1
     */
    public function dropTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule(StatementType::DropTable->value)->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate any PostgreSQL statement.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate simple statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->simpleStatement(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function simpleStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule(StatementType::SimpleStatement->value)->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL TRUNCATE statement.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate truncate statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->truncateStatement(maxDepth: 0);
     *     preg_match('/\bTRUNCATE\b/is', $sql) // => 1
     */
    public function truncateStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('TruncateStmt')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL COPY statement.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate copy statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->copyStatement(maxDepth: 0);
     *     preg_match('/\bCOPY\b/is', $sql) // => 1
     */
    public function copyStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::copyStatement()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL CREATE INDEX statement.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate create index statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->createIndexStatement(maxDepth: 0);
     *     preg_match('/\bCREATE\b.*\bINDEX\b/is', $sql) // => 1
     */
    public function createIndexStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('IndexStmt')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL transaction statement (BEGIN/COMMIT/ROLLBACK).
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate transaction statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->transactionStatement(maxDepth: 0);
     *     preg_match('/\b(?:ABORT|BEGIN|COMMIT|ROLLBACK|START|SAVEPOINT|RELEASE|PREPARE)\b/is', $sql) // => 1
     */
    public function transactionStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('TransactionStmt')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL expression.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate expr at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->expr(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function expr(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('a_expr')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a simple PostgreSQL expression.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate simple expr at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->simpleExpr(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function simpleExpr(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('c_expr')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL literal.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate literal at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->literal(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function literal(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('AexprConst')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL WHERE clause.
     *
     * @visibility public
     * @example An optional clause can be empty at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $provider->whereClause(maxDepth: 0) // => ''
     */
    public function whereClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('where_clause')->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL ORDER BY clause.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate sort clause at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->sortClause(maxDepth: 0);
     *     preg_match('/\bORDER\b.*\bBY\b/is', $sql) // => 1
     */
    public function sortClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('sort_clause')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL LIMIT clause.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate select limit at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->selectLimit(maxDepth: 0);
     *     preg_match('/\b(?:LIMIT|OFFSET|FETCH)\b/is', $sql) // => 1
     */
    public function selectLimit(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('select_limit')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL table reference.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate table ref at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->tableRef(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function tableRef(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('table_ref')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL joined table.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate joined table at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->joinedTable(maxDepth: 0);
     *     preg_match('/\bJOIN\b/is', $sql) // => 1
     */
    public function joinedTable(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('joined_table')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL qualified name (table identifier).
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate qualified name at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->qualifiedName(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function qualifiedName(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('qualified_name')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL subquery.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate subquery at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->subquery(maxDepth: 0);
     *     preg_match('/\(.*\bSELECT\b.*\)/is', $sql) // => 1
     */
    public function subquery(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('select_with_parens')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL WITH clause (CTE).
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate with clause at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->withClause(maxDepth: 0);
     *     preg_match('/\bWITH\b.*\bAS\b/is', $sql) // => 1
     */
    public function withClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('with_clause')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a named PostgreSQL foreign-key table constraint.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate foreign key constraint at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->foreignKeyConstraint(maxDepth: 0);
     *     preg_match('/\bCONSTRAINT\b.*\bFOREIGN\b.*\bREFERENCES\b/is', $sql) // => 1
     */
    public function foreignKeyConstraint(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::foreignKeyConstraint()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL identifier via grammar derivation.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate identifier at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->identifier(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function identifier(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('ColId')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a double-quote-quoted PostgreSQL identifier.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->quotedIdentifier(minLength: 4, maxLength: 4);
     *     preg_match('/^"[a-z_][a-z0-9_]{3}"$/', $token) // => 1
     */
    public function quotedIdentifier(int $minLength = 1, int $maxLength = 63): string
    {
        return $this->sql->generate(GenerationPlans::quotedIdentifier($minLength, $maxLength));
    }

    /**
     * Generate a PostgreSQL string literal.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->stringLiteral(minLength: 4, maxLength: 4);
     *     preg_match('/^\'[A-Za-z0-9_]{4}\'$/', $token) // => 1
     */
    public function stringLiteral(int $minLength = 1, int $maxLength = 255): string
    {
        return $this->sql->generate(GenerationPlans::stringLiteral($minLength, $maxLength));
    }

    /**
     * Generate a PostgreSQL integer literal.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Fix both bounds to obtain one value
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $provider->integerLiteral(min: 42, max: 42) // => '42'
     */
    public function integerLiteral(int $min = 1, int $max = 2147483647): string
    {
        return $this->sql->generate(GenerationPlans::integerLiteral($min, $max));
    }

    /**
     * Generate a PostgreSQL decimal literal.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->decimalLiteral(precision: 5, scale: 2);
     *     preg_match('/^[0-9]{1,3}\.[0-9]{2}$/', $token) // => 1
     */
    public function decimalLiteral(int $precision = 10, int $scale = 2): string
    {
        return $this->sql->generate(GenerationPlans::decimalLiteral($precision, $scale));
    }

    /**
     * Generate a PostgreSQL float literal with exponent (FCONST).
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->floatLiteral(precision: 5, scale: 2, minExponent: 3, maxExponent: 3);
     *     preg_match('/^[0-9]{1,3}\.[0-9]{2}e3$/', $token) // => 1
     */
    public function floatLiteral(int $precision = 10, int $scale = 2, int $minExponent = -307, int $maxExponent = 308): string
    {
        return $this->sql->generate(
            GenerationPlans::floatLiteral($precision, $scale, $minExponent, $maxExponent),
        );
    }

    /**
     * Generate a PostgreSQL hexadecimal literal (X'...' / XCONST).
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->hexLiteral(minLength: 4, maxLength: 4);
     *     preg_match('/^X\'[0-9a-f]{4}\'$/', $token) // => 1
     */
    public function hexLiteral(int $minLength = 1, int $maxLength = 16): string
    {
        return $this->sql->generate(GenerationPlans::hexLiteral($minLength, $maxLength));
    }

    /**
     * Generate a PostgreSQL bit string literal (B'...' / BCONST).
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->binaryLiteral(minLength: 4, maxLength: 4);
     *     preg_match('/^B\'[01]{4}\'$/', $token) // => 1
     */
    public function binaryLiteral(int $minLength = 1, int $maxLength = 64): string
    {
        return $this->sql->generate(GenerationPlans::binaryLiteral($minLength, $maxLength));
    }

    /**
     * Generate a PostgreSQL dollar-quoted string ($$...$$).
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->dollarQuotedString(minLength: 4, maxLength: 4);
     *     preg_match('/^\$\$[A-Za-z0-9_]{4}\$\$$/', $token) // => 1
     */
    public function dollarQuotedString(int $minLength = 1, int $maxLength = 255): string
    {
        return $this->sql->generate(GenerationPlans::dollarQuotedString($minLength, $maxLength));
    }

    /**
     * Generate a PostgreSQL parameter marker ($1, $2, etc.).
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Fix both bounds to obtain one value
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $provider->parameterMarker(min: 42, max: 42) // => '$42'
     */
    public function parameterMarker(int $min = 1, int $max = 99): string
    {
        return $this->sql->generate(GenerationPlans::parameterMarker($min, $max));
    }

    /**
     * Generate an upsert whose update expression calls a function.
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate insert function upsert statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->insertFunctionUpsertStatement(maxDepth: 0);
     *     preg_match('/\bINSERT\b.*\bCONFLICT\b.*\bUPDATE\b.*\(/is', $sql) // => 1
     */
    public function insertFunctionUpsertStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(
            GenerationPlans::insertFunctionUpsertStatement()->withMaxDepth($maxDepth),
        );
    }

    /**
     * Generate an upsert with a partial-index predicate.
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate partial index upsert statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->partialIndexUpsertStatement(maxDepth: 0);
     *     preg_match('/\bCONFLICT\b.*\bWHERE\b.*\bUPDATE\b/is', $sql) // => 1
     */
    public function partialIndexUpsertStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(
            GenerationPlans::partialIndexUpsertStatement()->withMaxDepth($maxDepth),
        );
    }

    /**
     * Generate an INSERT, UPDATE or DELETE for domain-oriented fuzzing.
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate domain dml statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->domainDmlStatement(maxDepth: 0);
     *     preg_match('/\b(?:INSERT|UPDATE|DELETE)\b/is', $sql) // => 1
     */
    public function domainDmlStatement(int $maxDepth = 40): string
    {
        /**
         * @var GenerationPlan<true> $plan
         */
        $plan = $this->generator->randomElement(GenerationPlans::domainDmlStatements());

        return $this->sql->generate($plan->withMaxDepth($maxDepth));
    }

    /**
     * Generate a query using the dialect full-text search syntax.
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate full text search statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->fullTextSearchStatement(maxDepth: 0);
     *     preg_match('/\bSELECT\b.*@@/is', $sql) // => 1
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
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
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
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
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
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
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
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->foreignKeyCascadeStatement(maxDepth: 0);
     *     preg_match('/\bREFERENCES\b.*\bCASCADE\b.*\bCASCADE\b/is', $sql) // => 1
     */
    public function foreignKeyCascadeStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::foreignKeyCascadeStatement()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a table partition declaration.
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate partition of statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->partitionOfStatement(maxDepth: 0);
     *     preg_match('/\bPARTITION\b.*\bOF\b.*\bVALUES\b/is', $sql) // => 1
     */
    public function partitionOfStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::partitionOfStatement()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a query with TABLESAMPLE.
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate table sample statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->tableSampleStatement(maxDepth: 0);
     *     preg_match('/\bTABLESAMPLE\b/is', $sql) // => 1
     */
    public function tableSampleStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::tableSampleStatement()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a DO statement.
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate do statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->doStatement(maxDepth: 0);
     *     preg_match('/\bDO\b/is', $sql) // => 1
     */
    public function doStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::doStatement()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a MERGE statement.
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate merge statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\PostgreSqlProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->mergeStatement(maxDepth: 0);
     *     preg_match('/\bMERGE\b.*\bMATCHED\b/is', $sql) // => 1
     */
    public function mergeStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::mergeStatement()->withMaxDepth($maxDepth));
    }
}
