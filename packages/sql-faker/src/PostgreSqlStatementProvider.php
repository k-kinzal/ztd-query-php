<?php

declare(strict_types=1);

namespace SqlFaker;

use Faker\Generator;
use Faker\Provider\Base;
use SqlFaker\Grammar\Walk\GenerationPlan;
use SqlFaker\PostgreSql\GenerationPlans;
use SqlFaker\PostgreSql\SqlGenerator;
use SqlFaker\PostgreSql\StatementRule;

/**
 * Faker provider for the statements a server is sent.
 *
 * Every kind of statement the grammar names, from the ones that read and write
 * rows to the ones that define tables, domains and transactions.
 *
 * PostgreSqlProvider registers this alongside itself, so a caller adds that
 * one provider and reaches these through the generator like any other Faker
 * method.
 */
final class PostgreSqlStatementProvider extends Base
{
    /** @readonly */
    private SqlGenerator $sql;

    /**
     * Binds the provider to the generator it answers through.
     *
     * @param Generator $generator Generator the methods are reached through
     * @param string|null $version Version tag to generate for, or null for the default
     * @param SqlGenerator|null $sql Generator to share, or null to build one for this provider alone
     */
    public function __construct(Generator $generator, ?string $version = null, ?SqlGenerator $sql = null)
    {
        parent::__construct($generator);

        $this->sql = $sql ?? SqlGenerator::for($generator, $version);

        $generator->addProvider($this);
    }

    /**
     * Generate a PostgreSQL SELECT statement.
     *
     * @return non-empty-string
     */
    public function selectStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule(StatementRule::Select->value)->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL INSERT statement.
     *
     * @return non-empty-string
     */
    public function insertStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule(StatementRule::Insert->value)->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL UPDATE statement.
     *
     * @return non-empty-string
     */
    public function updateStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule(StatementRule::Update->value)->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL DELETE statement.
     *
     * @return non-empty-string
     */
    public function deleteStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule(StatementRule::Delete->value)->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL CREATE TABLE statement.
     *
     * @return non-empty-string
     */
    public function createTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule(StatementRule::CreateTable->value)->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL CREATE TABLE AS statement.
     *
     * @return non-empty-string
     */
    public function createTableAsStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule(StatementRule::CreateTableAs->value)->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL CREATE DOMAIN statement.
     *
     * @return non-empty-string
     */
    public function createDomainStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(
            GenerationPlan::fromRule(StatementRule::CreateDomain->value)->requiringNonEmpty()->withMaxDepth($maxDepth),
        );
    }

    /**
     * Generate a PostgreSQL ALTER TABLE statement.
     *
     * @return non-empty-string
     */
    public function alterTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule(StatementRule::AlterTable->value)->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL DROP TABLE statement.
     *
     * @return non-empty-string
     */
    public function dropTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule(StatementRule::DropTable->value)->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate any PostgreSQL statement.
     *
     * @return non-empty-string
     */
    public function simpleStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule(StatementRule::SimpleStatement->value)->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL TRUNCATE statement.
     *
     * @return non-empty-string
     */
    public function truncateStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('TruncateStmt')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL COPY statement.
     *
     * @return non-empty-string
     */
    public function copyStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::copyStatement()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL CREATE INDEX statement.
     *
     * @return non-empty-string
     */
    public function createIndexStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('IndexStmt')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL transaction statement (BEGIN/COMMIT/ROLLBACK).
     *
     * @return non-empty-string
     */
    public function transactionStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('TransactionStmt')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a named PostgreSQL foreign-key table constraint.
     *
     * @return non-empty-string
     */
    public function foreignKeyConstraint(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::foreignKeyConstraint()->withMaxDepth($maxDepth));
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
    public function partialIndexUpsertStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(
            GenerationPlans::partialIndexUpsertStatement()->withMaxDepth($maxDepth),
        );
    }

    /**
     * @return non-empty-string
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
    public function partitionOfStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::partitionOfStatement()->withMaxDepth($maxDepth));
    }

    /**
     * @return non-empty-string
     */
    public function tableSampleStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::tableSampleStatement()->withMaxDepth($maxDepth));
    }

    /**
     * @return non-empty-string
     */
    public function doStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::doStatement()->withMaxDepth($maxDepth));
    }

    /**
     * @return non-empty-string
     */
    public function mergeStatement(int $maxDepth = 40): string
    {
        return $this->sql->generate(GenerationPlans::mergeStatement()->withMaxDepth($maxDepth));
    }
}
