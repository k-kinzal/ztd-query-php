<?php

declare(strict_types=1);

namespace SqlFaker;

use Faker\Generator;
use Faker\Provider\Base;
use SqlFaker\MySql\GenerationPlans;
use SqlFaker\MySql\SqlGenerator;

/**
 * Faker provider for the parts a statement is built from.
 *
 * The pieces a statement is assembled out of -- expressions, predicates, clauses
 * and the ways of naming a table -- generated on their own.
 *
 * MySqlProvider registers this alongside itself, so a caller adds that one
 * provider and reaches these through the generator like any other Faker method.
 */
final class MySqlFragmentProvider extends Base
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
     * Generate a MySQL expression.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler expression)
     * @return string Generated expression
     *
     * @example $faker->expr() // "col1 + 1"
     */
    public function expr(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('expr', $maxDepth));
    }

    /**
     * Generate a simple MySQL expression.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler expression)
     * @return string Generated simple expression
     *
     * @example $faker->simpleExpr() // "col1"
     */
    public function simpleExpr(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('simple_expr', $maxDepth));
    }

    /**
     * Generate a MySQL literal.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler literal)
     * @return string Generated literal
     *
     * @example $faker->literal() // "'hello'" or "123"
     */
    public function literal(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('literal', $maxDepth));
    }

    /**
     * Generate a MySQL predicate.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler predicate)
     * @return string Generated predicate
     *
     * @example $faker->predicate() // "col1 = 1"
     */
    public function predicate(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('predicate', $maxDepth));
    }

    /**
     * Generate a WHERE clause.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler clause)
     * @return string Generated WHERE clause
     *
     * @example $faker->whereClause() // "WHERE col1 = 1"
     */
    public function whereClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('where_clause', $maxDepth));
    }

    /**
     * Generate an ORDER BY clause.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler clause)
     * @return string Generated ORDER BY clause
     *
     * @example $faker->orderClause() // "ORDER BY col1 ASC"
     */
    public function orderClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('order_clause', $maxDepth));
    }

    /**
     * Generate a LIMIT clause.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler clause)
     * @return string Generated LIMIT clause
     *
     * @example $faker->limitClause() // "LIMIT 10"
     */
    public function limitClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('limit_clause', $maxDepth));
    }

    /**
     * Generate a table reference.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler reference)
     * @return string Generated table reference
     *
     * @example $faker->tableReference() // "t1 AS a"
     */
    public function tableReference(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('table_reference', $maxDepth));
    }

    /**
     * Generate a joined table.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler join)
     * @return string Generated joined table
     *
     * @example $faker->joinedTable() // "t1 JOIN t2 ON t1.id = t2.id"
     */
    public function joinedTable(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('joined_table', $maxDepth));
    }

    /**
     * Generate a table identifier.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler identifier)
     * @return string Generated table identifier
     *
     * @example $faker->tableIdent() // "db1.t1" or "t1"
     */
    public function tableIdent(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('table_ident', $maxDepth));
    }

    /**
     * Generate a subquery.
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler subquery)
     * @return string Generated subquery
     *
     * @example $faker->subquery() // "(SELECT * FROM t1)"
     */
    public function subquery(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('subquery', $maxDepth));
    }

    /**
     * Generate a WITH clause (CTE).
     *
     * @param int $maxDepth Maximum recursion depth (lower = simpler CTE)
     * @return string Generated WITH clause
     *
     * @example $faker->withClause() // "WITH cte AS (SELECT 1)"
     */
    public function withClause(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('with_clause', $maxDepth));
    }
}
