<?php

declare(strict_types=1);

namespace Tests\Integration\MySql;

use Container\Endpoint;
use Container\MySql80Container;
use Container\MySql84Container;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_mysql
 * @group integration
 * @group mysql
 */
#[CoversNothing]
#[Large]
final class TemporaryTableTest extends TestCase
{
    public function testDmlContinuesAcrossTemporaryTableLifecycle(): void
    {
        $endpoint = \Testcontainers\Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class)->getData(Endpoint::class);
        /** @var PDO $rawPdo */
        $rawPdo = new PDO(
            $endpoint->dsn(),
            $endpoint->username,
            $endpoint->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $rawPdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4', $databaseName));
        $rawPdo->exec(sprintf('USE `%s`', $databaseName));


        try {
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $ztdPdo->exec('CREATE TABLE source (id INT PRIMARY KEY, value VARCHAR(255))');
            $ztdPdo->exec("INSERT INTO source VALUES (1, 'a')");
            $ztdPdo->exec('CREATE TEMPORARY TABLE staging (id INT PRIMARY KEY, value VARCHAR(255))');
            $ztdPdo->exec('INSERT INTO staging SELECT * FROM source');
            $ztdPdo->exec("UPDATE staging SET value = 'b' WHERE id = 1");
            $ztdPdo->exec('DELETE FROM staging WHERE id = 1');
            $ztdPdo->exec("INSERT INTO staging VALUES (2, 'c')");
            $ztdPdo->exec('INSERT INTO source SELECT * FROM staging');

            $statement = $ztdPdo->query('SELECT * FROM source ORDER BY id');
            self::assertNotFalse($statement);
            self::assertSame(
                [['id' => 1, 'value' => 'a'], ['id' => 2, 'value' => 'c']],
                $statement->fetchAll(PDO::FETCH_ASSOC),
            );
        } finally {
            $rawPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
