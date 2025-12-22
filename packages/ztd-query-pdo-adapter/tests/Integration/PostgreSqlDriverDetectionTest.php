<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\PostgreSqlContainer;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Platform\Postgres\PgSqlSessionFactory;

/**
 * Integration tests for ZtdPdo driver auto-detection with a real PostgreSQL database.
 *
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
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();

        try {
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            self::assertTrue($ztdPdo->isZtdEnabled());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testExplicitSessionFactoryInjection(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();

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
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();

        try {
            $config = ZtdConfig::default();
            $ztdPdo = ZtdPdo::fromPdo($rawPdo, $config);

            self::assertTrue($ztdPdo->isZtdEnabled());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
