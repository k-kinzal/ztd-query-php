<?php

declare(strict_types=1);

namespace SqlFaker\Bench;

use Faker\Factory;
use PhpBench\Attributes as Benchmark;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\MySql\Generation\GenerationContext as MySqlContext;
use SqlFaker\MySql\Grammar\MySqlGrammar;
use SqlFaker\PostgreSql\Generation\GenerationContext as PostgreSqlContext;
use SqlFaker\PostgreSql\Grammar\PgGrammar;
use SqlFaker\Sqlite\Generation\GenerationContext as SqliteContext;
use SqlFaker\Sqlite\Grammar\SqliteGrammar;

/**
 * Measures SQL generation using each dialect's grammar and lexical definitions.
 */
final class Bench
{
    private SqlGenerator $generator;

    /** @var GenerationPlan<true> */
    private GenerationPlan $plan;

    /**
     * Prepares the generator and SELECT plan before measurement.
     * @param array{dialect: 'mysql'|'postgres'|'sqlite', rule: string} $params
     */
    public function setUp(array $params): void
    {
        $faker = Factory::create();
        $faker->seed(2001);
        $context = match ($params['dialect']) {
            'mysql' => new MySqlContext(MySqlGrammar::load('mysql-8.4.7')->identified(), $faker, 'mysql-8.4.7'),
            'postgres' => new PostgreSqlContext(PgGrammar::load('pg-17.2')->identified(), $faker, 'pg-17.2'),
            'sqlite' => new SqliteContext(SqliteGrammar::load('sqlite-3.47.2')->identified(), $faker, 'sqlite-3.47.2'),
        };
        $this->generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->rewriter, $context->startSymbol);
        $this->plan = GenerationPlan::fromRule($params['rule'])->withMaxDepth(6)->withStepBudget()->requiringNonEmpty();
    }

    /**
     * Lists the dialects and their SELECT start rules.
     * @return array<string, array{dialect: 'mysql'|'postgres'|'sqlite', rule: string}>
     */
    public function provideDialects(): array
    {
        return [
            'mysql' => ['dialect' => 'mysql', 'rule' => 'select_stmt'],
            'postgres' => ['dialect' => 'postgres', 'rule' => 'SelectStmt'],
            'sqlite' => ['dialect' => 'sqlite', 'rule' => 'select'],
        ];
    }

    /**
     * Generates a SELECT statement directly through the SQL engine.
     */
    #[Benchmark\BeforeMethods('setUp')]
    #[Benchmark\ParamProviders('provideDialects')]
    #[Benchmark\Revs(250)]
    public function benchSqlGeneration(): void
    {
        $this->generator->generate($this->plan);
    }
}
