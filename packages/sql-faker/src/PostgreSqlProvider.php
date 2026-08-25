<?php

declare(strict_types=1);

namespace SqlFaker;

use Faker\Generator;
use Faker\Provider\Base;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\PostgreSql\GenerationPlans;
use SqlFaker\PostgreSql\Grammar\PgGrammar;
use SqlFaker\PostgreSql\SqlGenerator;
use SqlFaker\PostgreSql\StatementRule;

/**
 * Faker Provider for generating syntactically valid PostgreSQL SQL statements.
 *
 * This provider uses PostgreSQL's official Bison grammar (gram.y) to generate
 * SQL that is syntactically valid. Note that generated SQL may not be semantically
 * valid (tables/columns may not exist).
 *
 * Usage:
 *   $faker = \Faker\Factory::create();
 *   $faker->addProvider(new \SqlFaker\PostgreSqlProvider($faker));
 *
 *   // Use specific PostgreSQL version
 *   $faker->addProvider(new \SqlFaker\PostgreSqlProvider($faker, 'pg-17.2'));
 *
 *   $faker->sql();
 *   $faker->selectStatement();
 *   $faker->insertStatement();
 */
final class PostgreSqlProvider extends Base
{
    private SqlGenerator $sql;

    /**
     * @param Generator $generator Faker generator
     * @param string|null $version PostgreSQL version tag. Null for default.
     */
    public function __construct(Generator $generator, ?string $version = null)
    {
        parent::__construct($generator);

        $generator->addProvider($this);

        $resolvedVersion = PgGrammar::resolveVersion($version);
        $this->sql = new SqlGenerator(PgGrammar::load($resolvedVersion), $generator, $resolvedVersion);
    }

    /**
     * Generate a syntactically valid PostgreSQL SQL statement.
     *
     * @param StatementRule|null $type Statement type (null for random)
     * @param int $maxDepth Maximum recursion depth (PHP_INT_MAX = unlimited)
     * @return non-empty-string Generated SQL statement
     */
    public function sql(?StatementRule $type = null, int $maxDepth = PHP_INT_MAX): string
    {
        if ($type === null) {
            /**
             * @var StatementRule $type
             */
            $type = $this->generator->randomElement(StatementRule::cases());
        }

        return $this->sql->generate(GenerationPlan::fromRule($type->value)->requiringNonEmpty()->withMaxDepth($maxDepth));
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
     * Generate a PostgreSQL expression.
     *
     * @return non-empty-string
     */
    public function expr(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('a_expr')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a simple PostgreSQL expression.
     *
     * @return non-empty-string
     */
    public function simpleExpr(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('c_expr')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL literal.
     *
     * @return non-empty-string
     */
    public function literal(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('AexprConst')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL WHERE clause.
     */
    public function whereClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('where_clause')->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL ORDER BY clause.
     *
     * @return non-empty-string
     */
    public function sortClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('sort_clause')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL LIMIT clause.
     *
     * @return non-empty-string
     */
    public function selectLimit(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('select_limit')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL table reference.
     *
     * @return non-empty-string
     */
    public function tableRef(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('table_ref')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL joined table.
     *
     * @return non-empty-string
     */
    public function joinedTable(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('joined_table')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL qualified name (table identifier).
     *
     * @return non-empty-string
     */
    public function qualifiedName(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('qualified_name')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL subquery.
     *
     * @return non-empty-string
     */
    public function subquery(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('select_with_parens')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a PostgreSQL WITH clause (CTE).
     *
     * @return non-empty-string
     */
    public function withClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('with_clause')->requiringNonEmpty()->withMaxDepth($maxDepth));
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
     * Generate a PostgreSQL identifier via grammar derivation.
     *
     * @param int $maxDepth Maximum recursion depth
     * @return non-empty-string
     */
    public function identifier(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('ColId')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a double-quote-quoted PostgreSQL identifier.
     *
     * @return non-empty-string
     */
    public function quotedIdentifier(int $minLength = 1, int $maxLength = 63): string
    {
        return $this->sql->generate(GenerationPlans::quotedIdentifier($minLength, $maxLength));
    }

    /**
     * Generate a PostgreSQL string literal.
     *
     * @return non-empty-string
     */
    public function stringLiteral(int $minLength = 1, int $maxLength = 255): string
    {
        return $this->sql->generate(GenerationPlans::stringLiteral($minLength, $maxLength));
    }

    /**
     * Generate a PostgreSQL integer literal.
     *
     * @return non-empty-string
     */
    public function integerLiteral(int $min = 1, int $max = 2147483647): string
    {
        return $this->sql->generate(GenerationPlans::integerLiteral($min, $max));
    }

    /**
     * Generate a PostgreSQL decimal literal.
     *
     * @return non-empty-string
     */
    public function decimalLiteral(int $precision = 10, int $scale = 2): string
    {
        return $this->sql->generate(GenerationPlans::decimalLiteral($precision, $scale));
    }

    /**
     * Generate a PostgreSQL float literal with exponent (FCONST).
     *
     * @return non-empty-string
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
     */
    public function hexLiteral(int $minLength = 1, int $maxLength = 16): string
    {
        return $this->sql->generate(GenerationPlans::hexLiteral($minLength, $maxLength));
    }

    /**
     * Generate a PostgreSQL bit string literal (B'...' / BCONST).
     *
     * @return non-empty-string
     */
    public function binaryLiteral(int $minLength = 1, int $maxLength = 64): string
    {
        return $this->sql->generate(GenerationPlans::binaryLiteral($minLength, $maxLength));
    }

    /**
     * Generate a PostgreSQL dollar-quoted string ($$...$$).
     *
     * @return non-empty-string
     */
    public function dollarQuotedString(int $minLength = 1, int $maxLength = 255): string
    {
        return $this->sql->generate(GenerationPlans::dollarQuotedString($minLength, $maxLength));
    }

    /**
     * Generate a PostgreSQL parameter marker ($1, $2, etc.).
     *
     * @return non-empty-string
     */
    public function parameterMarker(int $min = 1, int $max = 99): string
    {
        return $this->sql->generate(GenerationPlans::parameterMarker($min, $max));
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

    /**
     * @template TRequiresNonEmpty of bool
     * @param GenerationPlan<TRequiresNonEmpty> $plan
     * @return (TRequiresNonEmpty is true ? non-empty-string : string)
     */
}
