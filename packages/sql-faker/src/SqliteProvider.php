<?php

declare(strict_types=1);

namespace SqlFaker;

use Faker\Generator;
use Faker\Provider\Base;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\Provider\SqlGeneratorFactory;
use SqlFaker\Sqlite\GenerationPlans;
use SqlFaker\Sqlite\Grammar\SqliteGrammar;
use SqlFaker\Sqlite\StatementType;

/**
 * Faker Provider for generating syntactically valid SQLite SQL statements.
 *
 * This provider uses SQLite's official Lemon grammar (parse.y) to generate
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
 *     $faker->addProvider(new \SqlFaker\SqliteProvider($faker));
 *     $faker->integerLiteral(min: 42, max: 42) // => '42'
 */
final class SqliteProvider extends Base
{
    private SqlGenerator $sql;

    /**
     * Register SQL formatters on the supplied Faker generator.
     * @param Generator $generator Faker generator
     * @param string|null $version SQLite version tag. Null for default.
     *
     * @visibility public
     * @example Choose a supported database version
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker, 'sqlite-3.47.2');
     *     $provider->integerLiteral(min: 42, max: 42) // => '42'
     * @example Reject an unsupported database version
     *     new \SqlFaker\SqliteProvider(\Faker\Factory::create(), 'missing-version') // throws \RuntimeException: Unsupported
     */
    public function __construct(Generator $generator, ?string $version = null)
    {
        parent::__construct($generator);

        $generator->addProvider($this);
        $resolvedVersion = SqliteGrammar::resolveVersion($version);
        $this->sql = SqlGeneratorFactory::forSqlite($generator, SqliteGrammar::load($resolvedVersion), $resolvedVersion);
    }

    /**
     * Generate a syntactically valid SQLite SQL statement.
     *
     * @param StatementType|null $type Statement type (null for random)
     *
     * @visibility public
     * @example Select a statement type explicitly
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->sql(\SqlFaker\Sqlite\StatementType::Select, maxDepth: 0);
     *     preg_match('/\b(SELECT|VALUES)\b/i', $sql) // => 1
     */
    public function sql(?StatementType $type = null, int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statementOfType($this->generator, $type, $maxDepth));
    }

    /**
     * Generate a SQLite SELECT statement.
     *
     * @visibility public
     * @example Generate select statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->selectStatement(maxDepth: 0);
     *     preg_match('/\b(SELECT|VALUES)\b/is', $sql) // => 1
     */
    public function selectStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement(StatementType::Select->value, $maxDepth));
    }

    /**
     * Generate a SQLite INSERT statement.
     *
     * @visibility public
     * @example Generate insert statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->insertStatement(maxDepth: 0);
     *     preg_match('/\b(INSERT|REPLACE)\b/is', $sql) // => 1
     */
    public function insertStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement(StatementType::Insert->value, $maxDepth));
    }

    /**
     * Generate a SQLite UPDATE statement.
     *
     * @visibility public
     * @example Generate update statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->updateStatement(maxDepth: 0);
     *     preg_match('/\bUPDATE\b/is', $sql) // => 1
     */
    public function updateStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement(StatementType::Update->value, $maxDepth));
    }

    /**
     * Generate a SQLite DELETE statement.
     *
     * @visibility public
     * @example Generate delete statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->deleteStatement(maxDepth: 0);
     *     preg_match('/\bDELETE\b/is', $sql) // => 1
     */
    public function deleteStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement(StatementType::Delete->value, $maxDepth));
    }

    /**
     * Generate a SQLite CREATE TABLE statement.
     *
     * @visibility public
     * @example Generate create table statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->createTableStatement(maxDepth: 0);
     *     preg_match('/\bCREATE\b.*\bTABLE\b/is', $sql) // => 1
     */
    public function createTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement(StatementType::CreateTable->value, $maxDepth));
    }

    /**
     * Generate a SQLite ALTER TABLE statement.
     *
     * @visibility public
     * @example Generate alter table statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->alterTableStatement(maxDepth: 0);
     *     preg_match('/\bALTER\b.*\bTABLE\b/is', $sql) // => 1
     */
    public function alterTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement(StatementType::AlterTable->value, $maxDepth));
    }

    /**
     * Generate a SQLite DROP TABLE statement.
     *
     * @visibility public
     * @example Generate drop table statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->dropTableStatement(maxDepth: 0);
     *     preg_match('/\bDROP\b.*\bTABLE\b/is', $sql) // => 1
     */
    public function dropTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement(StatementType::DropTable->value, $maxDepth));
    }

    /**
     * Generate any SQLite statement.
     *
     * @visibility public
     * @example Generate simple statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->simpleStatement(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function simpleStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement(StatementType::SimpleStatement->value, $maxDepth));
    }

    /**
     * Generate a SQLite expression.
     *
     * @visibility public
     * @example Generate expr at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->expr(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function expr(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('expr', $maxDepth));
    }

    /**
     * Generate a simple SQLite expression (term).
     *
     * @visibility public
     * @example Generate term at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->term(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function term(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('term', $maxDepth));
    }

    /**
     * Generate a SQLite WHERE clause.
     *
     * @visibility public
     * @example An optional clause can be empty at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $provider->whereClause(maxDepth: 0) // => ''
     */
    public function whereClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('where_opt', $maxDepth));
    }

    /**
     * Generate a SQLite ORDER BY clause.
     *
     * @visibility public
     * @example An optional clause can be empty at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $provider->orderByClause(maxDepth: 0) // => ''
     */
    public function orderByClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('orderby_opt', $maxDepth));
    }

    /**
     * Generate a SQLite LIMIT clause.
     *
     * @visibility public
     * @example An optional clause can be empty at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $provider->limitClause(maxDepth: 0) // => ''
     */
    public function limitClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('limit_opt', $maxDepth));
    }

    /**
     * Generate a SQLite GROUP BY clause.
     *
     * @visibility public
     * @example An optional clause can be empty at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $provider->groupByClause(maxDepth: 0) // => ''
     */
    public function groupByClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('groupby_opt', $maxDepth));
    }

    /**
     * Generate a SQLite HAVING clause.
     *
     * @visibility public
     * @example An optional clause can be empty at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $provider->havingClause(maxDepth: 0) // => ''
     */
    public function havingClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('having_opt', $maxDepth));
    }

    /**
     * Generate a SQLite full table name.
     *
     * @visibility public
     * @example Generate fullname at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->fullname(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function fullname(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('fullname', $maxDepth));
    }

    /**
     * Generate a SQLite WITH clause (CTE).
     *
     * @visibility public
     * @example An optional clause can be empty at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $provider->withClause(maxDepth: 0) // => ''
     */
    public function withClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('with', $maxDepth));
    }

    /**
     * Generate a named SQLite foreign-key table constraint.
     *
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate foreign key constraint at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->foreignKeyConstraint(maxDepth: 0);
     *     preg_match('/\bCONSTRAINT\b.*\bFOREIGN\b.*\bREFERENCES\b/is', $sql) // => 1
     */
    public function foreignKeyConstraint(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::foreignKeyConstraint()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a SQLite identifier via grammar derivation.
     *
     * @visibility public
     * @example Generate identifier at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->identifier(maxDepth: 0);
     *     $sql !== '' // => true
     */
    public function identifier(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('nm', $maxDepth));
    }

    /**
     * Generate a double-quote-quoted SQLite identifier.
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->quotedIdentifier(minLength: 4, maxLength: 4);
     *     preg_match('/^"[a-z_][a-z0-9_]{3}"$/', $token) // => 1
     */
    public function quotedIdentifier(int $minLength = 1, int $maxLength = 128): string
    {
        return $this->sql->generate(GenerationPlans::quotedIdentifier($minLength, $maxLength));
    }

    /**
     * Generate a SQLite string literal.
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->stringLiteral(minLength: 4, maxLength: 4);
     *     preg_match('/^\'[A-Za-z0-9_]{4}\'$/', $token) // => 1
     */
    public function stringLiteral(int $minLength = 1, int $maxLength = 255): string
    {
        return $this->sql->generate(GenerationPlans::stringLiteral($minLength, $maxLength));
    }

    /**
     * Generate a SQLite integer literal.
     *
     * @visibility public
     * @example Fix both bounds to obtain one value
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $provider->integerLiteral(min: 42, max: 42) // => '42'
     */
    public function integerLiteral(int $min = 1, int $max = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::integerLiteral($min, $max));
    }

    /**
     * Generate a SQLite decimal literal.
     *
     * @visibility public
     * @example Constrain the generated token shape
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $token = $provider->decimalLiteral(precision: 5, scale: 2);
     *     preg_match('/^[0-9]{1,3}\.[0-9]{2}$/', $token) // => 1
     */
    public function decimalLiteral(int $precision = 15, int $scale = 2): string
    {
        return $this->sql->generate(GenerationPlans::decimalLiteral($precision, $scale));
    }

    /**
     * Generate an upsert whose update expression calls a function.
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate insert function upsert statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
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
     * Generate two semicolon-terminated DML statements.
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate multi dml statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->multiDmlStatement(maxDepth: 0);
     *     preg_match('/;.*;/is', $sql) // => 1
     */
    public function multiDmlStatement(int $maxDepth = 40): string
    {
        $plan = GenerationPlans::multiDmlStatement(
            $this->generator->numberBetween(0, 2),
            $this->generator->numberBetween(0, 2),
        );

        return $this->sql->generate($plan->withMaxDepth($maxDepth));
    }

    /**
     * Generate a query using the dialect full-text search syntax.
     * @return non-empty-string
     *
     * @visibility public
     * @example Generate full text search statement at the shortest depth
     *     $faker = \Faker\Factory::create();
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->fullTextSearchStatement(maxDepth: 0);
     *     preg_match('/\bSELECT\b.*\bMATCH\b/is', $sql) // => 1
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
     *     $provider = new \SqlFaker\SqliteProvider($faker);
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
     *     $provider = new \SqlFaker\SqliteProvider($faker);
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
     *     $provider = new \SqlFaker\SqliteProvider($faker);
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
     *     $provider = new \SqlFaker\SqliteProvider($faker);
     *     $faker->seed(7);
     *     $sql = $provider->foreignKeyCascadeStatement(maxDepth: 0);
     *     preg_match('/\bREFERENCES\b.*\bCASCADE\b.*\bCASCADE\b/is', $sql) // => 1
     */
    public function foreignKeyCascadeStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::foreignKeyCascadeStatement()->withMaxDepth($maxDepth));
    }
}
