<?php

declare(strict_types=1);

namespace SqlFaker;

use Faker\Generator;
use Faker\Provider\Base;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar as ContractGrammar;
use SqlFaker\Contract\Runtime;
use SqlFaker\Grammar\ContractGrammarProjector;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\PostgreSql\Grammar\PgGrammar;
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
final class PostgreSqlProvider extends Base implements Runtime
{
    private \SqlFaker\Grammar\Grammar $grammar;
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

        $this->rsg = new RandomStringGenerator($generator);
        $this->grammar = PgGrammar::load($version);
        $this->sql = new SqlGenerator($this->grammar, $generator, $this);
    }

    public function snapshot(): ContractGrammar
    {
        return ContractGrammarProjector::project($this->grammar, NonTerminal::class);
    }

    public function supportedGrammar(): ContractGrammar
    {
        return ContractGrammarProjector::project($this->sql->compiledGrammar(), NonTerminal::class);
    }

    public function generate(GenerationRequest $request): string
    {
        if ($request->seed !== null) {
            $this->generator->seed($request->seed);
        }

        return $this->sql->generate($request->startRule, $request->maxDepth);
    }

    /**
     * Generate a syntactically valid PostgreSQL SQL statement.
     *
     * @param StatementType|null $type Statement type (null for random)
     * @param int $maxDepth Maximum recursion depth (PHP_INT_MAX = unlimited)
     * @return string Generated SQL statement
     */
    public function sql(?StatementType $type = null, int $maxDepth = PHP_INT_MAX): string
    {
        if ($type === null) {
            /** @var StatementType $type */
            $type = $this->generator->randomElement(StatementType::cases());
        }

        return $this->generate(new GenerationRequest(startRule: $type->value, maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL SELECT statement.
     */
    public function selectStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: StatementType::Select->value, maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL INSERT statement.
     */
    public function insertStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: StatementType::Insert->value, maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL UPDATE statement.
     */
    public function updateStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: StatementType::Update->value, maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL DELETE statement.
     */
    public function deleteStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: StatementType::Delete->value, maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL CREATE TABLE statement.
     */
    public function createTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: StatementType::CreateTable->value, maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL ALTER TABLE statement.
     */
    public function alterTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: StatementType::AlterTable->value, maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL DROP TABLE statement.
     */
    public function dropTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: StatementType::DropTable->value, maxDepth: $maxDepth));
    }

    /**
     * Generate any PostgreSQL statement.
     */
    public function simpleStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: StatementType::SimpleStatement->value, maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL TRUNCATE statement.
     */
    public function truncateStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'TruncateStmt', maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL CREATE INDEX statement.
     */
    public function createIndexStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'IndexStmt', maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL transaction statement (BEGIN/COMMIT/ROLLBACK).
     */
    public function transactionStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'TransactionStmt', maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL expression.
     */
    public function expr(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'a_expr', maxDepth: $maxDepth));
    }

    /**
     * Generate a simple PostgreSQL expression.
     */
    public function simpleExpr(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'c_expr', maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL literal.
     */
    public function literal(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'AexprConst', maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL WHERE clause.
     */
    public function whereClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'where_clause', maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL ORDER BY clause.
     */
    public function sortClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'sort_clause', maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL LIMIT clause.
     */
    public function selectLimit(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'select_limit', maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL table reference.
     */
    public function tableRef(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'table_ref', maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL joined table.
     */
    public function joinedTable(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'joined_table', maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL qualified name (table identifier).
     */
    public function qualifiedName(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'qualified_name', maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL subquery.
     */
    public function subquery(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'select_with_parens', maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL WITH clause (CTE).
     */
    public function withClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'with_clause', maxDepth: $maxDepth));
    }

    /**
     * Generate a PostgreSQL identifier via grammar derivation.
     *
     * @param int $maxDepth Maximum recursion depth
     */
    public function identifier(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'ColId', maxDepth: $maxDepth));
    }

    /**
     * Generate a double-quote-quoted PostgreSQL identifier.
     */
    public function quotedIdentifier(int $minLength = 1, int $maxLength = 63): string
    {
        return '"' . $this->rsg->rawIdentifier($minLength, $maxLength) . '"';
    }

    /**
     * Generate a PostgreSQL string literal.
     */
    public function stringLiteral(int $minLength = 1, int $maxLength = 32): string
    {
        return "'" . $this->rsg->mixedAlnumString($minLength, $maxLength) . "'";
    }

    /**
     * Generate a PostgreSQL integer literal.
     */
    public function integerLiteral(int $min = 1, int $max = 2147483647): string
    {
        return $this->rsg->integerString($min, $max);
    }

    /**
     * Generate a PostgreSQL decimal literal.
     */
    public function decimalLiteral(int $precision = 10, int $scale = 2): string
    {
        return $this->rsg->decimalString($precision, $scale);
    }

    /**
     * Generate a PostgreSQL float literal with exponent (FCONST).
     */
    public function floatLiteral(int $precision = 10, int $scale = 2, int $minExponent = -307, int $maxExponent = 308): string
    {
        return $this->rsg->floatString($this->decimalLiteral($precision, $scale), $minExponent, $maxExponent);
    }

    /**
     * Generate a PostgreSQL hexadecimal literal (X'...' / XCONST).
     */
    public function hexLiteral(int $minLength = 1, int $maxLength = 16): string
    {
        return "X'" . $this->rsg->hexString($minLength, $maxLength) . "'";
    }

    /**
     * Generate a PostgreSQL bit string literal (B'...' / BCONST).
     */
    public function binaryLiteral(int $minLength = 1, int $maxLength = 64): string
    {
        return "B'" . $this->rsg->binaryString($minLength, $maxLength) . "'";
    }

    /**
     * Generate a PostgreSQL dollar-quoted string ($$...$$).
     */
    public function dollarQuotedString(int $minLength = 1, int $maxLength = 32): string
    {
        return '$$' . $this->rsg->mixedAlnumString($minLength, $maxLength) . '$$';
    }

    /**
     * Generate a deterministic DO body that parses under PostgreSQL's default plpgsql language.
     */
    public function doBodyLiteral(): string
    {
        return "'BEGIN NULL; END'";
    }

    /**
     * Generate a PostgreSQL parameter marker ($1, $2, etc.).
     */
    public function parameterMarker(int $min = 1, int $max = 99): string
    {
        return '$' . $this->rsg->parameterIndex($min, $max);
    }
}
