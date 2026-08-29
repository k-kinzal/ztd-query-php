<?php

declare(strict_types=1);

namespace SqlFaker;

use Faker\Generator;
use Faker\Provider\Base;
use SqlFaker\Grammar\Walk\GenerationPlan;
use SqlFaker\PostgreSql\SqlGenerator;

/**
 * Faker provider for the parts a statement is built from.
 *
 * The pieces a statement is assembled out of, generated on their own.
 *
 * PostgreSqlProvider registers this alongside itself, so a caller adds that
 * one provider and reaches these through the generator like any other Faker
 * method.
 */
final class PostgreSqlFragmentProvider extends Base
{
    /** @readonly */
    private SqlGenerator $sql;

    /**
     * Binds the provider to the generator it answers through.
     *
     * @param Generator $generator Generator the methods are reached through
     * @param string|null $version Version tag to generate for, or null for the default
     */
    public function __construct(Generator $generator, ?string $version = null)
    {
        parent::__construct($generator);

        $this->sql = SqlGenerator::for($generator, $version);

        $generator->addProvider($this);
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
}
