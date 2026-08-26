<?php

declare(strict_types=1);

namespace Tests\Integration\MySql;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\MySqlContainer;
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
        [$databaseName, $rawPdo] = MySqlContainer::createTestDatabase();

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
