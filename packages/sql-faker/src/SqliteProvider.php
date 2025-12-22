<?php

declare(strict_types=1);

namespace SqlFaker;

use Faker\Generator;
use Faker\Provider\Base;
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
final class SqliteProvider extends Base
{
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
        $this->sql = new SqlGenerator(SqliteGrammar::load($version), $generator, $this);
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

        return $this->sql->generate($type->value, $maxDepth);
    }

    /**
     * Generate a SQLite SELECT statement.
     */
    public function selectStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(StatementType::Select->value, $maxDepth);
    }

    /**
     * Generate a SQLite INSERT statement.
     */
    public function insertStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(StatementType::Insert->value, $maxDepth);
    }

    /**
     * Generate a SQLite UPDATE statement.
     */
    public function updateStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(StatementType::Update->value, $maxDepth);
    }

    /**
     * Generate a SQLite DELETE statement.
     */
    public function deleteStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(StatementType::Delete->value, $maxDepth);
    }

    /**
     * Generate a SQLite CREATE TABLE statement.
     */
    public function createTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(StatementType::CreateTable->value, $maxDepth);
    }

    /**
     * Generate a SQLite ALTER TABLE statement.
     */
    public function alterTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(StatementType::AlterTable->value, $maxDepth);
    }

    /**
     * Generate a SQLite DROP TABLE statement.
     */
    public function dropTableStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(StatementType::DropTable->value, $maxDepth);
    }

    /**
     * Generate any SQLite statement.
     */
    public function simpleStatement(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(StatementType::SimpleStatement->value, $maxDepth);
    }

    /**
     * Generate a SQLite expression.
     */
    public function expr(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('expr', $maxDepth);
    }

    /**
     * Generate a simple SQLite expression (term).
     */
    public function term(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('term', $maxDepth);
    }

    /**
     * Generate a SQLite WHERE clause.
     */
    public function whereClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('where_opt', $maxDepth);
    }

    /**
     * Generate a SQLite ORDER BY clause.
     */
    public function orderByClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('orderby_opt', $maxDepth);
    }

    /**
     * Generate a SQLite LIMIT clause.
     */
    public function limitClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('limit_opt', $maxDepth);
    }

    /**
     * Generate a SQLite GROUP BY clause.
     */
    public function groupByClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('groupby_opt', $maxDepth);
    }

    /**
     * Generate a SQLite HAVING clause.
     */
    public function havingClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('having_opt', $maxDepth);
    }

    /**
     * Generate a SQLite full table name.
     */
    public function fullname(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('fullname', $maxDepth);
    }

    /**
     * Generate a SQLite WITH clause (CTE).
     */
    public function withClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('with', $maxDepth);
    }

    /**
     * Generate a SQLite identifier via grammar derivation.
     *
     * @param int $maxDepth Maximum recursion depth
     */
    public function identifier(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate('nm', $maxDepth);
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
    public function stringLiteral(int $minLength = 1, int $maxLength = 255): string
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
