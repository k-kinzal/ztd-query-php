<?php

declare(strict_types=1);

namespace Bench;

use Faker\Factory;
use PhpBench\Attributes as Bench;
use SqlFaker\MySqlProvider;
use SqlFaker\PostgreSqlProvider;
use SqlFaker\SqliteProvider;

#[Bench\Groups(['bootstrap'])]
final class ProviderBootstrapBench
{
    #[Bench\Revs(20)]
    public function benchMySqlProviderBootstrap(): void
    {
        $faker = Factory::create();
        $faker->seed(1001);
        new MySqlProvider($faker, 'mysql-8.4.7');
    }

    #[Bench\Revs(20)]
    public function benchPostgreSqlProviderBootstrap(): void
    {
        $faker = Factory::create();
        $faker->seed(1002);
        new PostgreSqlProvider($faker, 'pg-17.2');
    }

    #[Bench\Revs(20)]
    public function benchSqliteProviderBootstrap(): void
    {
        $faker = Factory::create();
        $faker->seed(1003);
        new SqliteProvider($faker, 'sqlite-3.47.2');
    }
}
