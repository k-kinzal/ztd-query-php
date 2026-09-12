<?php

declare(strict_types=1);

namespace Tests\Fixture;

use PDO;

/**
 * Opens isolated test sessions against the database services configured by CI.
 */
final class Database
{
    /**
     * Opens MySQL with native prepares; temporary tables live only for this session.
     */
    public static function mysql(): PDO
    {
        $dsn = getenv('SQL_FIXTURE_MYSQL_DSN');
        $username = getenv('SQL_FIXTURE_MYSQL_USER');
        $password = getenv('SQL_FIXTURE_MYSQL_PASSWORD');
        return new PDO(
            $dsn !== false ? $dsn : 'mysql:host=127.0.0.1;port=13316;dbname=sql_fixture',
            $username !== false ? $username : 'root',
            $password !== false ? $password : 'toolkit',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
    }

    /**
     * Opens PostgreSQL; temporary schemas isolate concurrent tests and disappear on close.
     */
    public static function postgres(): PDO
    {
        $dsn = getenv('SQL_FIXTURE_PGSQL_DSN');
        $username = getenv('SQL_FIXTURE_PGSQL_USER');
        $password = getenv('SQL_FIXTURE_PGSQL_PASSWORD');
        return new PDO(
            $dsn !== false ? $dsn : 'pgsql:host=127.0.0.1;port=15432;dbname=sql_fixture',
            $username !== false ? $username : 'postgres',
            $password !== false ? $password : 'toolkit',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
    }
}
