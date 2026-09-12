<?php

declare(strict_types=1);

namespace Bench;

use Faker\Factory;
use PhpBench\Attributes as Bench;
use SqlFixture\FixtureProvider;
use SqlFixture\Plan\FixturePlan;

/**
 * Measures warm row generation separately from relational-plan materialization.
 */
#[Bench\Groups(['fixtures'])]
#[Bench\BeforeMethods('setUp')]
#[Bench\Revs(5000)]
final class FixtureGenerationBench
{
    private const ITEMS_SQL = 'CREATE TABLE items (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(80) NOT NULL, price DECIMAL(8,2) NOT NULL)';

    private FixtureProvider $provider;
    private FixturePlan $plan;

    /**
     * Warms schema and plan caches outside the measured operation, then seeds the random source.
     */
    public function setUp(): void
    {
        $faker = Factory::create();
        $this->provider = new FixtureProvider($faker);
        $this->provider->registerSchema(self::ITEMS_SQL);
        $this->provider->registerSchema('CREATE TABLE details (item_id INT NOT NULL, quantity INT NOT NULL)');
        $this->plan = FixturePlan::from('items.id < details.item_id');
        $faker->seed(2026);
    }

    /**
     * Generates one row through the cached public provider.
     */
    public function benchWarmRow(): void
    {
        $this->provider->fixture(self::ITEMS_SQL);
    }

    /**
     * Generates a fixed three-child relation without reparsing the plan.
     */
    public function benchRelatedRows(): void
    {
        $this->provider->fixtures($this->plan, ['details' => 3]);
    }
}
