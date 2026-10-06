<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use Faker\Factory;
use Faker\Generator;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Choice\PlanBuilder;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\MySql\MySqlProvider;
use SqlFixture\Provider\FixtureGenerator;
use SqlFixture\Provider\PlatformFactory;
use SqlFixture\Schema\Exception\InvalidSqlException;
use SqlFixture\Schema\Exception\MissingColumnDefinitionsException;
use SqlFixture\Schema\SchemaParserInterface;

/**
 * Mutates SQL structure and lexical choices, then checks schema acceptance and the generated row contract.
 */
final class CreateTableTarget
{
    private readonly Generator $faker;
    private readonly MySqlProvider $sqlProvider;
    private readonly PlanBuilder $planner;
    private readonly SchemaParserInterface $schemaParser;

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
        $this->schemaParser = PlatformFactory::createSchemaParser(PlatformFactory::DRIVER_MYSQL, $grammarVersion);
        $this->schemaParser->parse('CREATE TABLE warm_up (id INT PRIMARY KEY)');
        $this->constraints = GenerationPlan::constrained('create_table_stmt', [
            'create_table_stmt' => [ProductionPattern::containing('table_element_list')],
        ])->requiringNonEmpty()->withExpansionBudget($maxExpansions);
    }

    /**
     * Accepted schemas must generate exactly their writable columns, including explicit overrides.
     *
     * The grammar admits statements the server refuses, such as a key on a
     * column the table lacks; the parser rejects those for a diagnosed reason.
     * A statement the grammar generated must never fail to parse.
     *
     * @throws Error When generated fixture columns differ from the parsed schema
     */
    public function __invoke(string $input): void
    {
        $plan = (new BytePlanCompiler())->compile($input, $this->planner, $this->constraints);
        $sql = $this->sqlProvider->generate($plan);
        try {
            $schema = $this->schemaParser->parse($sql);
        } catch (InvalidSqlException $exception) {
            if ($exception->getPrevious() !== null) {
                throw new Error('Generated statement failed to parse; input=' . bin2hex($input) . "\nSQL: " . $sql, 0, $exception);
            }

            return;
        } catch (MissingColumnDefinitionsException) {
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
