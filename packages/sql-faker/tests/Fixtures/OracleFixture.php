<?php

declare(strict_types=1);

namespace Tests\Fixtures\SqlFaker;

use PDO;
use PgSql\Connection;
use PHPUnit\Framework\Assert;
use SqlFaker\Fuzz\Target\InfrastructureFailure;
use SqlFaker\Fuzz\Target\OracleEnvironment;

/**
 * Connects native oracle controls only to explicitly configured disposable engines.
 */
final class OracleFixture
{
    /**
     * Makes missing optional infrastructure visible as skipped native controls in the ordinary unit CI job.
     */
    public static function requiredSetting(string $name): string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            Assert::markTestSkipped('Native oracle control requires ' . $name . '.');
        }
        return $value;
    }

    /**
     * @throws InfrastructureFailure When the configured PostgreSQL engine is unavailable or incompatible
     */
    public static function pg(): Connection
    {
        $connection = pg_connect(self::requiredSetting('SQLFAKER_PG_CONNECTION'));
        if ($connection === false) {
            throw new InfrastructureFailure('Cannot connect to the native PostgreSQL control engine.');
        }
        OracleEnvironment::pg($connection);
        return $connection;
    }

    /**
     * @throws InfrastructureFailure When the configured MySQL engine has an incompatible scanner environment
     */
    public static function mysql(): PDO
    {
        $pdo = new PDO(self::requiredSetting('SQLFAKER_MYSQL_DSN'), OracleEnvironment::setting('SQLFAKER_MYSQL_USER', 'root'), OracleEnvironment::setting('SQLFAKER_MYSQL_PASSWORD', 'root'));
        OracleEnvironment::mysql($pdo, 'mysql-8.4.7');
        return $pdo;
    }
}
