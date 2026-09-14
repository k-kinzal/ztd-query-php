<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use Faker\Factory;
use Faker\Generator;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Choice\PlanBuilder;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\MySqlProvider;
use SqlFixture\FixtureGenerator;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Schema\SchemaParseException;

/**
 * Mutates SQL structure and lexical choices, then checks the accepted-schema row contract.
 */
final class CreateTableTarget
{
    private readonly Generator $faker;
    private readonly MySqlProvider $sqlProvider;
    private readonly PlanBuilder $planner;

    /**
     * @var GenerationPlan<bool>
     */
    private readonly GenerationPlan $constraints;

    /**
     * Bounds grammar expansion while keeping raw input choices available to the planner.
     */
    public function __construct(private readonly string $grammarVersion, int $maxExpansions = 128)
    {
        $this->faker = Factory::create();
        $this->sqlProvider = new MySqlProvider($this->faker, $grammarVersion);
        $this->planner = $this->sqlProvider->planner();
        $this->constraints = GenerationPlan::fromRule('create_table_stmt')->requiringNonEmpty()->withExpansionBudget($maxExpansions);
    }

    /**
     * Accepted schemas must generate exactly their writable columns, including explicit overrides.
     *
     * @throws Error When generated fixture columns differ from the parsed schema
     */
    public function __invoke(string $input): void
    {
        $plan = (new BytePlanCompiler())->compile($input, $this->planner, $this->constraints);
        $sql = $this->sqlProvider->generate($plan);
        try {
            $schema = (new MySqlSchemaParser())->parse($sql);
        } catch (SchemaParseException) {
            return;
        }
        $this->faker->seed(crc32(str_pad($input, 4, "\0")));
        $generator = new FixtureGenerator($this->faker);
        $row = $generator->generate($schema);
        $writable = array_filter($schema->columns, static fn ($column): bool => !$column->autoIncrement && !$column->generated);
        if (array_keys($row) !== array_keys($writable)) {
            throw new Error("Writable column mismatch; grammar={$this->grammarVersion}; input=" . bin2hex($input) . "\nSQL: " . $sql);
        }
        $overridden = $generator->generate($schema, $row);
        if ($overridden !== $row) {
            throw new Error('Override preservation mismatch; input=' . bin2hex($input) . "\nSQL: " . $sql);
        }
    }
}
