<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Container\PostgreSqlContainer;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Platform\Postgres\PgSqlSessionFactory;

/**
 * @requires extension pdo_pgsql
 * @group integration
 * @group postgres
 */
#[CoversNothing]
#[Large]
final class PostgreSqlDriverDetectionTest extends TestCase
{
    public function testAutoDetectionCreatesPgSqlSession(): void
    {
        $containerInstance = \Testcontainers\Testcontainers::run(PostgreSqlContainer::class);
        /** @var PDO $rawPdo */
        $rawPdo = $containerInstance->getData(PDO::class);

        $schemaName = 'ztd_' . bin2hex(random_bytes(8));
        $rawPdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
        $rawPdo->exec(sprintf('SET search_path TO "%s"', $schemaName));


        try {
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            self::assertTrue($ztdPdo->isZtdEnabled());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testExplicitSessionFactoryInjection(): void
    {
        $containerInstance = \Testcontainers\Testcontainers::run(PostgreSqlContainer::class);
        /** @var PDO $rawPdo */
        $rawPdo = $containerInstance->getData(PDO::class);

        $schemaName = 'ztd_' . bin2hex(random_bytes(8));
        $rawPdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
        $rawPdo->exec(sprintf('SET search_path TO "%s"', $schemaName));


        try {
            $factory = new PgSqlSessionFactory();
            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null, $factory);

            self::assertTrue($ztdPdo->isZtdEnabled());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testCustomConfigPassedToSession(): void
    {
        $containerInstance = \Testcontainers\Testcontainers::run(PostgreSqlContainer::class);
        /** @var PDO $rawPdo */
        $rawPdo = $containerInstance->getData(PDO::class);

        $schemaName = 'ztd_' . bin2hex(random_bytes(8));
        $rawPdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
        $rawPdo->exec(sprintf('SET search_path TO "%s"', $schemaName));


        try {
            $config = ZtdConfig::default();
            $ztdPdo = ZtdPdo::fromPdo($rawPdo, $config);

            self::assertTrue($ztdPdo->isZtdEnabled());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
