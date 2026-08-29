<?php

declare(strict_types=1);

namespace SqlFaker;

use Faker\Generator;
use Faker\Provider\Base;
use SqlFaker\MySql\GenerationPlans;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\SqlGenerator;
use SqlFaker\MySql\StatementRule;

/**
 * Faker Provider for generating syntactically valid MySQL SQL statements.
 *
 * This provider uses MySQL's official Bison grammar (sql_yacc.yy) to generate
 * SQL that is syntactically valid. Note that generated SQL may not be semantically
 * valid (tables/columns may not exist).
 *
 * Supported MySQL versions:
 *   - mysql-5.6.51
 *   - mysql-5.7.44
 *   - mysql-8.0.44
 *   - mysql-8.1.0
 *   - mysql-8.2.0
 *   - mysql-8.3.0
 *   - mysql-8.4.7 (default)
 *   - mysql-9.0.1
 *   - mysql-9.1.0
 *
 * Usage:
 *   $faker = \Faker\Factory::create();
 *   $faker->addProvider(new \SqlFaker\MySqlProvider($faker));
 *
 *   // Use specific MySQL version
 *   $faker->addProvider(new \SqlFaker\MySqlProvider($faker, 'mysql-5.7.44'));
 *
 *   // Generic SQL
 *   $faker->sql();
 *
 *   // Specific statement types
 *   $faker->selectStatement();
 *   $faker->insertStatement();
 *   $faker->updateStatement();
 *   $faker->deleteStatement();
 *
 *   // With start rule and maxDepth
 *   $faker->sql(StatementRule::Select);
 *   $faker->sql(StatementRule::Insert, maxDepth: 6);
 */
final class MySqlProvider extends Base
{
    private Grammar $grammar;
    private SqlGenerator $sql;

    /**
     * @param Generator $generator Faker generator
     * @param string|null $version MySQL version tag (e.g., "mysql-8.4.7"). Null for default.
     */
    public function __construct(Generator $generator, ?string $version = null)
    {
        parent::__construct($generator);

        $generator->addProvider($this);

        $resolvedVersion = Grammar::resolveVersion($version);
        $this->grammar = Grammar::load($resolvedVersion);
        $this->sql = new SqlGenerator($this->grammar, $generator, $resolvedVersion);

        new MySqlLiteralProvider($generator, $version, $this->sql);
        new MySqlFragmentProvider($generator, $version, $this->sql);
        new MySqlStatementProvider($generator, $version, $this->sql);
    }

    /**
     * Generate a syntactically valid SQL statement.
     *
     * @param StatementRule|null $startRule Start rule (null for default)
     * @param int $maxDepth Maximum recursion depth (PHP_INT_MAX = unlimited)
     * @return string Generated SQL statement
     *
     * @example $faker->sql() // Any valid MySQL statement
     * @example $faker->sql(StatementRule::Select) // Generates a SELECT statement
     * @example $faker->sql(StatementRule::Insert, maxDepth: 6) // Generates simpler INSERT
     */
    public function sql(?StatementRule $startRule = null, int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement($startRule?->value, $maxDepth));
    }

    /**
     * Generate SQL while requiring every MySQL row-value production to be non-empty.
     */
    public function sqlWithoutEmptyRows(
        ?StatementRule $startRule = null,
        int $maxDepth = PHP_INT_MAX,
    ): string {
        return $this->sql->generate(
            GenerationPlans::withoutEmptyRows($startRule?->value)->withMaxDepth($maxDepth),
        );
    }

}
