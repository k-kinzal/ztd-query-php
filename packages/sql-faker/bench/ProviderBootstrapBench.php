<?php

declare(strict_types=1);

namespace Bench;

use Faker\Factory;
use SqlFaker\MySqlProvider;
use SqlFaker\PostgreSqlProvider;
use SqlFaker\SqliteProvider;

final class ProviderBootstrapBench
{
    /**
     * @Revs(20)
     * @Iterations(5)
     */
    public function benchMySqlProviderBootstrap(): void
    {
        $faker = Factory::create();
        $faker->seed(1001);
        new MySqlProvider($faker);
    }

    /**
     * @Revs(20)
     * @Iterations(5)
     */
    public function benchPostgreSqlProviderBootstrap(): void
    {
        $faker = Factory::create();
        $faker->seed(1002);
        new PostgreSqlProvider($faker);
    }

    /**
     * @Revs(20)
     * @Iterations(5)
     */
    public function benchSqliteProviderBootstrap(): void
    {
        $faker = Factory::create();
        $faker->seed(1003);
        new SqliteProvider($faker);
    }
}
