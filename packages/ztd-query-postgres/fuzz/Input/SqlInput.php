<?php

declare(strict_types=1);

namespace Fuzz\Input;

use Faker\Generator;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\PostgreSqlProvider;

/**
 * Maps corpus bytes to general grammar statements and focused PostgreSQL features.
 */
final class SqlInput
{
    /**
     * Retains the seeded generator used by targeted PostgreSQL statements.
     */
    public function __construct(private readonly Generator $faker, private readonly PostgreSqlProvider $provider)
    {
    }

    /**
     * Compiles structural and lexical decisions from individual input bytes.
     */
    public function grammar(string $input): string
    {
        $constraints = GenerationPlan::fromRule('stmt')->requiringNonEmpty()->withExpansionBudget(1000);
        $plan = (new BytePlanCompiler())->compile(substr($input, 1), $this->provider->planner(), $constraints);
        return $this->provider->generate($plan);
    }

    /**
     * Produces the classify target SQL for one reproducible corpus input.
     */
    public function classify(string $input): string
    {
        $this->faker->seed(crc32(str_pad($input, 4, "\x00")));
        $generators = [fn (): string => $this->grammar($input), fn (): string => $this->provider->selectStatement(maxDepth: 8), fn (): string => $this->provider->insertStatement(maxDepth: 8), fn (): string => $this->provider->updateStatement(maxDepth: 8), fn (): string => $this->provider->deleteStatement(maxDepth: 8), fn (): string => $this->provider->createTableStatement(maxDepth: 5), fn (): string => $this->provider->alterTableStatement(maxDepth: 5), fn (): string => $this->provider->dropTableStatement(maxDepth: 3), fn (): string => $this->provider->partitionOfStatement(), fn (): string => $this->provider->tableSampleStatement(), fn (): string => $this->provider->doStatement(), fn (): string => $this->provider->mergeStatement(), fn (): string => $this->provider->copyStatement(maxDepth: 8), fn (): string => $this->provider->partialIndexUpsertStatement(), fn (): string => $this->provider->createDomainStatement(maxDepth: 8), fn (): string => $this->provider->domainDmlStatement(), fn (): string => $this->provider->fullTextSearchStatement()];
        $index = ord($input[0] ?? "\x00") % count($generators);
        return $generators[$index]();
    }

    /**
     * Produces the rewrite target SQL for one reproducible corpus input.
     */
    public function rewrite(string $input): string
    {
        $this->faker->seed(crc32(str_pad($input, 4, "\x00")));
        $generators = [fn (): string => $this->grammar($input), fn (): string => $this->provider->selectStatement(maxDepth: 8), fn (): string => $this->provider->insertStatement(maxDepth: 8), fn (): string => $this->provider->updateStatement(maxDepth: 8), fn (): string => $this->provider->deleteStatement(maxDepth: 8), fn (): string => $this->provider->createTableStatement(maxDepth: 5), fn (): string => $this->provider->alterTableStatement(maxDepth: 5), fn (): string => $this->provider->dropTableStatement(maxDepth: 3), fn (): string => $this->provider->truncateStatement(maxDepth: 8), fn (): string => $this->provider->insertFunctionUpsertStatement(), fn (): string => $this->provider->temporaryTableStatement(), fn (): string => $this->provider->viewStatement(), fn (): string => $this->provider->generatedColumnStatement(), fn (): string => $this->provider->foreignKeyCascadeStatement(), fn (): string => $this->provider->partitionOfStatement(), fn (): string => $this->provider->tableSampleStatement(), fn (): string => $this->provider->doStatement(), fn (): string => $this->provider->mergeStatement(), fn (): string => $this->provider->copyStatement(maxDepth: 8), fn (): string => $this->provider->partialIndexUpsertStatement(), fn (): string => $this->provider->createDomainStatement(maxDepth: 8), fn (): string => $this->provider->domainDmlStatement(), fn (): string => $this->provider->fullTextSearchStatement()];
        $index = ord($input[0] ?? "\x00") % count($generators);
        return $generators[$index]();
    }

    /**
     * Produces the full target SQL for one reproducible corpus input.
     */
    public function full(string $input): string
    {
        $this->faker->seed(crc32(str_pad($input, 4, "\x00")));
        $generators = [fn (): string => $this->grammar($input), fn (): string => $this->provider->selectStatement(maxDepth: 8), fn (): string => $this->provider->insertStatement(maxDepth: 8), fn (): string => $this->provider->updateStatement(maxDepth: 8), fn (): string => $this->provider->deleteStatement(maxDepth: 8), fn (): string => $this->provider->createTableStatement(maxDepth: 5), fn (): string => $this->provider->alterTableStatement(maxDepth: 5), fn (): string => $this->provider->dropTableStatement(maxDepth: 3), fn (): string => 'EXPLAIN (FORMAT JSON) SELECT * FROM users', fn (): string => 'SELECT * FROM public.users', fn (): string => $this->provider->partitionOfStatement(), fn (): string => $this->provider->tableSampleStatement(), fn (): string => $this->provider->doStatement(), fn (): string => $this->provider->mergeStatement(), fn (): string => $this->provider->copyStatement(maxDepth: 8), fn (): string => $this->provider->partialIndexUpsertStatement(), fn (): string => $this->provider->createDomainStatement(maxDepth: 8), fn (): string => $this->provider->domainDmlStatement(), fn (): string => $this->provider->fullTextSearchStatement()];
        $index = ord($input[0] ?? "\x00") % count($generators);
        return $generators[$index]();
    }

}
