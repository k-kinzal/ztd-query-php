<?php

declare(strict_types=1);

namespace SqlFaker;

use Faker\Generator;
use Faker\Provider\Base;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\PostgreSql\Grammar\PgGrammar;
use SqlFaker\PostgreSql\GenerationPlans;
use SqlFaker\PostgreSql\SqlGenerator;
use SqlFaker\PostgreSql\StatementType;

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
    private RandomStringGenerator $rsg;

    /**
     * @param Generator $generator Faker generator
     * @param string|null $version PostgreSQL version tag. Null for default.
     */
    public function __construct(Generator $generator, ?string $version = null)
    {
        parent::__construct($generator);

        $generator->addProvider($this);

        $resolvedVersion = PgGrammar::resolveVersion($version);
        $this->rsg = new RandomStringGenerator($generator);
        $this->sql = new SqlGenerator(PgGrammar::load($resolvedVersion), $generator, $this, $resolvedVersion);
    }

    /**
     * Generate a syntactically valid PostgreSQL SQL statement.
     *
     * @param StatementType|null $type Statement type (null for random)
     * @param int $maxDepth Maximum recursion depth (PHP_INT_MAX = unlimited)
     * @return non-empty-string Generated SQL statement
     */
    public function sql(?StatementType $type = null, int $maxDepth = PHP_INT_MAX): string
    {
        if ($type === null) {
            /** @var StatementType $type */
            $type = $this->generator->randomElement(StatementType::cases());
        }

        return $this->generate(GenerationPlan::fromRule($type->value)->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL SELECT statement.
     *
     * @return non-empty-string
     */
    public function selectStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule(StatementType::Select->value)->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL INSERT statement.
     *
     * @return non-empty-string
     */
    public function insertStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule(StatementType::Insert->value)->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL UPDATE statement.
     *
     * @return non-empty-string
     */
    public function updateStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule(StatementType::Update->value)->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL DELETE statement.
     *
     * @return non-empty-string
     */
    public function deleteStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule(StatementType::Delete->value)->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL CREATE TABLE statement.
     *
     * @return non-empty-string
     */
    public function createTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule(StatementType::CreateTable->value)->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL CREATE TABLE AS statement.
     *
     * @return non-empty-string
     */
    public function createTableAsStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule(StatementType::CreateTableAs->value)->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL ALTER TABLE statement.
     *
     * @return non-empty-string
     */
    public function alterTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule(StatementType::AlterTable->value)->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL DROP TABLE statement.
     *
     * @return non-empty-string
     */
    public function dropTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule(StatementType::DropTable->value)->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate any PostgreSQL statement.
     *
     * @return non-empty-string
     */
    public function simpleStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule(StatementType::SimpleStatement->value)->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL TRUNCATE statement.
     *
     * @return non-empty-string
     */
    public function truncateStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule('TruncateStmt')->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL CREATE INDEX statement.
     *
     * @return non-empty-string
     */
    public function createIndexStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule('IndexStmt')->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL transaction statement (BEGIN/COMMIT/ROLLBACK).
     *
     * @return non-empty-string
     */
    public function transactionStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule('TransactionStmt')->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL expression.
     *
     * @return non-empty-string
     */
    public function expr(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule('a_expr')->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a simple PostgreSQL expression.
     *
     * @return non-empty-string
     */
    public function simpleExpr(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule('c_expr')->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL literal.
     *
     * @return non-empty-string
     */
    public function literal(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule('AexprConst')->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL WHERE clause.
     */
    public function whereClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule('where_clause'), $maxDepth);
    }

    /**
     * Generate a PostgreSQL ORDER BY clause.
     *
     * @return non-empty-string
     */
    public function sortClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule('sort_clause')->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL LIMIT clause.
     *
     * @return non-empty-string
     */
    public function selectLimit(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule('select_limit')->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL table reference.
     *
     * @return non-empty-string
     */
    public function tableRef(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule('table_ref')->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL joined table.
     *
     * @return non-empty-string
     */
    public function joinedTable(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule('joined_table')->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL qualified name (table identifier).
     *
     * @return non-empty-string
     */
    public function qualifiedName(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule('qualified_name')->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL subquery.
     *
     * @return non-empty-string
     */
    public function subquery(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule('select_with_parens')->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a PostgreSQL WITH clause (CTE).
     *
     * @return non-empty-string
     */
    public function withClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(GenerationPlan::fromRule('with_clause')->requiringNonEmpty(), $maxDepth);
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
        return $this->generate(GenerationPlan::fromRule('ColId')->requiringNonEmpty(), $maxDepth);
    }

    /**
     * Generate a double-quote-quoted PostgreSQL identifier.
     *
     * @return non-empty-string
     */
    public function quotedIdentifier(int $minLength = 1, int $maxLength = 63): string
    {
        return '"' . $this->rsg->rawIdentifier($minLength, $maxLength) . '"';
    }

    /**
     * Generate a PostgreSQL string literal.
     *
     * @return non-empty-string
     */
    public function stringLiteral(int $minLength = 1, int $maxLength = 255): string
    {
        return "'" . $this->rsg->mixedAlnumString($minLength, $maxLength) . "'";
    }

    /**
     * Generate a PostgreSQL integer literal.
     *
     * @return non-empty-string
     */
    public function integerLiteral(int $min = 1, int $max = 2147483647): string
    {
        return $this->rsg->integerString($min, $max);
    }

    /**
     * Generate a PostgreSQL decimal literal.
     *
     * @return non-empty-string
     */
    public function decimalLiteral(int $precision = 10, int $scale = 2): string
    {
        return $this->rsg->decimalString($precision, $scale);
    }

    /**
     * Generate a PostgreSQL float literal with exponent (FCONST).
     *
     * @return non-empty-string
     */
    public function floatLiteral(int $precision = 10, int $scale = 2, int $minExponent = -307, int $maxExponent = 308): string
    {
        return $this->rsg->floatString($this->decimalLiteral($precision, $scale), $minExponent, $maxExponent);
    }

    /**
     * Generate a PostgreSQL hexadecimal literal (X'...' / XCONST).
     *
     * @return non-empty-string
     */
    public function hexLiteral(int $minLength = 1, int $maxLength = 16): string
    {
        return "X'" . $this->rsg->hexString($minLength, $maxLength) . "'";
    }

    /**
     * Generate a PostgreSQL bit string literal (B'...' / BCONST).
     *
     * @return non-empty-string
     */
    public function binaryLiteral(int $minLength = 1, int $maxLength = 64): string
    {
        return "B'" . $this->rsg->binaryString($minLength, $maxLength) . "'";
    }

    /**
     * Generate a PostgreSQL dollar-quoted string ($$...$$).
     *
     * @return non-empty-string
     */
    public function dollarQuotedString(int $minLength = 1, int $maxLength = 255): string
    {
        return '$$' . $this->rsg->mixedAlnumString($minLength, $maxLength) . '$$';
    }

    /**
     * Generate a PostgreSQL parameter marker ($1, $2, etc.).
     *
     * @return non-empty-string
     */
    public function parameterMarker(int $min = 1, int $max = 99): string
    {
        return '$' . $this->rsg->parameterIndex($min, $max);
    }

    /** @return non-empty-string */
    public function insertFunctionUpsertStatement(): string
    {
        $table = $this->rsg->rawIdentifier();
        $keyColumn = $this->rsg->rawIdentifier();
        $valueColumn = $this->rsg->rawIdentifier();

        return "INSERT INTO $table ($keyColumn, $valueColumn) VALUES (1, '{}') ON CONFLICT ($keyColumn) DO UPDATE SET $valueColumn = jsonb_set($table.$valueColumn, '{status}', '\"updated\"')";
    }

    /**
     * @template TRequiresNonEmpty of bool
     * @param GenerationPlan<TRequiresNonEmpty> $plan
     * @return (TRequiresNonEmpty is true ? non-empty-string : string)
     */
    private function generate(GenerationPlan $plan, int $maxDepth): string
    {
        return $this->sql->generate($plan->withMaxDepth($maxDepth));
    }
}
