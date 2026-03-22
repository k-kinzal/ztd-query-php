<?php

declare(strict_types=1);

namespace SqlFaker;

use Faker\Generator;
use Faker\Provider\Base;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Generation\FakerRandomSource;
use SqlFaker\Sqlite\LexicalValueGenerator;
use SqlFaker\Sqlite\LexicalValueSource;
use SqlFaker\Sqlite\StatementGenerator as SqliteStatementGenerator;
use SqlFaker\Sqlite\StatementType;

/**
 * Faker Provider for generating SQLite SQL from the documented supported language.
 *
 * This provider compiles a constrained grammar from SQLite's upstream Lemon
 * grammar snapshot and generates SQL through the runtime algorithm contract.
 * Generated SQL is syntax-oriented fuzzing input, not a guarantee of semantic
 * validity against a live schema.
 *
 * Usage:
 *   $faker = \Faker\Factory::create();
 *   $faker->addProvider(new \SqlFaker\SqliteProvider($faker));
 *
 *   // Use specific SQLite version
 *   $faker->addProvider(new \SqlFaker\SqliteProvider($faker, 'sqlite-3.47.2'));
 *
 *   $faker->sql();
 *   $faker->selectStatement();
 *   $faker->insertStatement();
 */
final class SqliteProvider extends Base implements LexicalValueSource
{
    private LexicalValueGenerator $lexicalValues;
    private SqliteStatementGenerator $statementGenerator;

    /**
     * @param Generator $generator Faker generator
     * @param string|null $version SQLite version tag. Null for default.
     */
    public function __construct(Generator $generator, ?string $version = null)
    {
        parent::__construct($generator);

        $generator->addProvider($this);

        $this->lexicalValues = new LexicalValueGenerator(new FakerRandomSource($generator));
        $this->statementGenerator = new SqliteStatementGenerator($generator, $version, $this->lexicalValues);
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
     * Generate a SQLite SQL statement from the supported-language contract.
     *
     * @param StatementType|null $type Statement type (null uses the default `cmd` entry rule)
     * @param int $maxDepth Maximum recursion depth (PHP_INT_MAX = unlimited)
     * @return string Generated SQL statement
     */
    public function sql(?StatementType $type = null, int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(
            startRule: $type !== null ? $type->value : 'cmd',
            maxDepth: $maxDepth,
        ));
    }

    /**
     * Generate a SQLite SELECT statement.
     */
    public function selectStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: StatementType::Select->value, maxDepth: $maxDepth));
    }

    /**
     * Generate a SQLite INSERT statement.
     */
    public function insertStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: StatementType::Insert->value, maxDepth: $maxDepth));
    }

    /**
     * Generate a SQLite UPDATE statement.
     */
    public function updateStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: StatementType::Update->value, maxDepth: $maxDepth));
    }

    /**
     * Generate a SQLite DELETE statement.
     */
    public function deleteStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: StatementType::Delete->value, maxDepth: $maxDepth));
    }

    /**
     * Generate a SQLite CREATE TABLE statement.
     */
    public function createTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: StatementType::CreateTable->value, maxDepth: $maxDepth));
    }

    /**
     * Generate a SQLite ALTER TABLE statement.
     */
    public function alterTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: StatementType::AlterTable->value, maxDepth: $maxDepth));
    }

    /**
     * Generate a SQLite DROP TABLE statement.
     */
    public function dropTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: StatementType::DropTable->value, maxDepth: $maxDepth));
    }

    /**
     * Generate any SQLite statement.
     */
    public function simpleStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: StatementType::SimpleStatement->value, maxDepth: $maxDepth));
    }

    /**
     * Generate a SQLite expression.
     */
    public function expr(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'expr', maxDepth: $maxDepth));
    }

    /**
     * Generate a simple SQLite expression (term).
     */
    public function term(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'term', maxDepth: $maxDepth));
    }

    /**
     * Generate a SQLite WHERE clause.
     */
    public function whereClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'where_opt', maxDepth: $maxDepth));
    }

    /**
     * Generate a SQLite ORDER BY clause.
     */
    public function orderByClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'orderby_opt', maxDepth: $maxDepth));
    }

    /**
     * Generate a SQLite LIMIT clause.
     */
    public function limitClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'limit_opt', maxDepth: $maxDepth));
    }

    /**
     * Generate a SQLite GROUP BY clause.
     */
    public function groupByClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'groupby_opt', maxDepth: $maxDepth));
    }

    /**
     * Generate a SQLite HAVING clause.
     */
    public function havingClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'having_opt', maxDepth: $maxDepth));
    }

    /**
     * Generate a SQLite full table name.
     */
    public function fullname(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'fullname', maxDepth: $maxDepth));
    }

    /**
     * Generate a SQLite WITH clause (CTE).
     */
    public function withClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'with', maxDepth: $maxDepth));
    }

    /**
     * Generate a SQLite identifier via grammar derivation.
     *
     * @param int $maxDepth Maximum recursion depth
     */
    public function identifier(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->generate(new GenerationRequest(startRule: 'nm', maxDepth: $maxDepth));
    }

    /**
     * Generate a double-quote-quoted SQLite identifier.
     */
    public function quotedIdentifier(int $minLength = 1, int $maxLength = 128): string
    {
        return $this->lexicalValues->quotedIdentifier($minLength, $maxLength);
    }

    /**
     * Generate a SQLite string literal.
     */
    public function stringLiteral(int $minLength = 1, int $maxLength = 32): string
    {
        return $this->lexicalValues->stringLiteral($minLength, $maxLength);
    }

    /**
     * Generate a SQLite integer literal.
     */
    public function integerLiteral(int $min = 1, int $max = PHP_INT_MAX): string
    {
        return $this->lexicalValues->integerLiteral($min, $max);
    }

    /**
     * Generate a SQLite decimal literal.
     */
    public function decimalLiteral(int $precision = 15, int $scale = 2): string
    {
        return $this->lexicalValues->decimalLiteral($precision, $scale);
    }
}
