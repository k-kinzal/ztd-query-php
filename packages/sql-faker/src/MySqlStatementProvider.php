<?php

declare(strict_types=1);

namespace SqlFaker;

use Faker\Generator;
use Faker\Provider\Base;
use SqlFaker\MySql\GenerationPlans;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\SqlGenerator;
use SqlFaker\MySql\StatementRule;

/**
 * Faker provider for the statements a server is sent.
 *
 * Every kind of statement the grammar names, from the four that read and write
 * rows to the ones that define tables, indexes and transactions.
 *
 * MySqlProvider registers this alongside itself, so a caller adds that one
 * provider and reaches these through the generator like any other Faker method.
 */
final class MySqlStatementProvider extends Base
{
    /** @readonly */
    private SqlGenerator $sql;

    /** @readonly */
    private Grammar $grammar;

    /**
     * Binds the provider to the generator it answers through.
     *
     * @param Generator $generator Generator the methods are reached through
     * @param string|null $version Version tag to generate for, or null for the default
     */
    public function __construct(Generator $generator, ?string $version = null)
    {
        parent::__construct($generator);

        $this->grammar = Grammar::load(Grammar::resolveVersion($version));
        $this->sql = SqlGenerator::for($generator, $version);

        $generator->addProvider($this);
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
        return $this->sql->generate(GenerationPlans::statement(StatementRule::Select->value, $maxDepth));
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
        return $this->sql->generate(GenerationPlans::statement(StatementRule::Insert->value, $maxDepth));
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
        return $this->sql->generate(GenerationPlans::statement(StatementRule::Update->value, $maxDepth));
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
        return $this->sql->generate(GenerationPlans::statement(StatementRule::Delete->value, $maxDepth));
    }

    /**
     * Generate a LOAD DATA statement from MySQL's official load_stmt rule.
     */
    public function loadDataStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::loadDataStatement()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a two-target UPDATE statement.
     */
    public function multiTableUpdateStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::multiTableUpdateStatement()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a two-target DELETE statement.
     */
    public function multiTableDeleteStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::multiTableDeleteStatement()->withMaxDepth($maxDepth));
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
        return $this->sql->generate(GenerationPlans::statement(StatementRule::CreateTable->value, $maxDepth));
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
        return $this->sql->generate(GenerationPlans::statement(StatementRule::AlterTable->value, $maxDepth));
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
        return $this->sql->generate(GenerationPlans::statement(StatementRule::DropTable->value, $maxDepth));
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
        return $this->sql->generate(GenerationPlans::statement(StatementRule::SimpleStatement->value, $maxDepth));
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
        return $this->sql->generate(GenerationPlans::statement('replace_stmt', $maxDepth));
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
        return $this->sql->generate(GenerationPlans::statement('truncate_stmt', $maxDepth));
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
        return $this->sql->generate(GenerationPlans::statement('create_index_stmt', $maxDepth));
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
        return $this->sql->generate(GenerationPlans::statement('drop_index_stmt', $maxDepth));
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
        return $this->sql->generate(GenerationPlans::statement('begin_stmt', $maxDepth));
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
        return $this->sql->generate(GenerationPlans::statement('commit', $maxDepth));
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
        return $this->sql->generate(GenerationPlans::statement('rollback', $maxDepth));
    }

    /**
     * Generate a named MySQL foreign-key table constraint.
     *
     * @return non-empty-string
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
     */
    public function insertRowAliasUpsertStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(
            GenerationPlans::insertRowAliasUpsertStatement()->withMaxDepth($maxDepth),
        );
    }

    /**
     * @return non-empty-string
     */
    public function insertFunctionUpsertStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(
            GenerationPlans::insertFunctionUpsertStatement()->withMaxDepth($maxDepth),
        );
    }

    /**
     * @return non-empty-string
     */
    public function fullTextSearchStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::fullTextSearchStatement()->withMaxDepth($maxDepth));
    }

    /**
     * @return non-empty-string
     */
    public function temporaryTableStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(
            GenerationPlans::temporaryTableStatement()->withMaxDepth($maxDepth),
        );
    }

    /**
     * @return non-empty-string
     */
    public function viewStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::viewStatement()->withMaxDepth($maxDepth));
    }

    /**
     * @return non-empty-string
     */
    public function generatedColumnStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::generatedColumnStatement()->withMaxDepth($maxDepth));
    }

    /**
     * @return non-empty-string
     */
    public function foreignKeyCascadeStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::foreignKeyCascadeStatement()->withMaxDepth($maxDepth));
    }

    /**
     * @return non-empty-string
     */
    public function partitionSelectStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::partitionSelectStatement()->withMaxDepth($maxDepth));
    }
}
