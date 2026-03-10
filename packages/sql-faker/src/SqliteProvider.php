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
use SqlFaker\Sqlite\Grammar\SqliteGrammar;
use SqlFaker\Sqlite\SqlGenerator;
use SqlFaker\Sqlite\StatementType;

/**
 * Faker Provider for generating syntactically valid SQLite SQL statements.
 *
 * This provider uses SQLite's official Lemon grammar (parse.y) to generate
 * SQL that is syntactically valid. Note that generated SQL may not be semantically
 * valid (tables/columns may not exist).
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
final class SqliteProvider extends Base implements Runtime
{
    private \SqlFaker\Grammar\Grammar $grammar;
    private SqlGenerator $sql;
    private RandomStringGenerator $rsg;

    /**
     * @param Generator $generator Faker generator
     * @param string|null $version SQLite version tag. Null for default.
     */
    public function __construct(Generator $generator, ?string $version = null)
    {
        parent::__construct($generator);

        $generator->addProvider($this);

        $this->rsg = new RandomStringGenerator($generator);
        $this->grammar = SqliteGrammar::load($version);
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
     * Generate a syntactically valid SQLite SQL statement.
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
        return '"' . $this->rsg->rawIdentifier($minLength, $maxLength) . '"';
    }

    /**
     * Generate a SQLite string literal.
     */
    public function stringLiteral(int $minLength = 1, int $maxLength = 32): string
    {
        return "'" . $this->rsg->mixedAlnumString($minLength, $maxLength) . "'";
    }

    /**
     * Generate a SQLite integer literal.
     */
    public function integerLiteral(int $min = 1, int $max = PHP_INT_MAX): string
    {
        return $this->rsg->integerString($min, $max);
    }

    /**
     * Generate a SQLite decimal literal.
     */
    public function decimalLiteral(int $precision = 15, int $scale = 2): string
    {
        return $this->rsg->decimalString($precision, $scale);
    }
}
