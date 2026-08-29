<?php

declare(strict_types=1);

namespace SqlFaker;

use Faker\Generator;
use Faker\Provider\Base;
use SqlFaker\Grammar\Walk\GenerationPlan;
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

        $this->sql = SqlGenerator::for($generator, $version);

        new PostgreSqlLiteralProvider($generator, $version, $this->sql);
        new PostgreSqlFragmentProvider($generator, $version, $this->sql);
        new PostgreSqlStatementProvider($generator, $version, $this->sql);
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
     * @template TRequiresNonEmpty of bool
     * @param GenerationPlan<TRequiresNonEmpty> $plan
     * @return (TRequiresNonEmpty is true ? non-empty-string : string)
     */
}
