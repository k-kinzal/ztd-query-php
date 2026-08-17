<?php

declare(strict_types=1);

namespace Tests\Integration\MySql;

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
final class ViewTest extends TestCase
{
    public function testViewsReadShadowWritesAcrossFiltersJoinsAggregatesAndPreparation(): void
    {
        [$databaseName, $pdo] = MySqlContainer::createTestDatabase();

        try {
            $pdo->exec('CREATE TABLE accounts (id INT PRIMARY KEY, region VARCHAR(20), amount INT, active BOOLEAN)');
            $pdo->exec('CREATE TABLE regions (code VARCHAR(20) PRIMARY KEY, label VARCHAR(20))');
            $pdo->exec('CREATE VIEW active_accounts AS SELECT id, region, amount FROM accounts WHERE active = 1');
            $pdo->exec('CREATE VIEW account_labels AS SELECT a.id, r.label, a.amount FROM active_accounts a JOIN regions r ON r.code = a.region');
            $pdo->exec('CREATE VIEW region_totals AS SELECT region, COUNT(*) AS account_count, SUM(amount) AS total_amount FROM active_accounts GROUP BY region');
            $ztdPdo = ZtdPdo::fromPdo($pdo);

            $ztdPdo->exec("INSERT INTO accounts VALUES (1, 'north', 100, 1), (2, 'south', 200, 0), (3, 'north', 300, 1)");
            $ztdPdo->exec("INSERT INTO regions VALUES ('north', 'North'), ('south', 'South')");

            $simple = $ztdPdo->query('SELECT id FROM active_accounts ORDER BY id');
            self::assertNotFalse($simple);
            self::assertSame([1, 3], $simple->fetchAll(\PDO::FETCH_COLUMN));

            $prepared = $ztdPdo->prepare('SELECT id FROM active_accounts WHERE amount >= ? ORDER BY id');
            self::assertNotFalse($prepared);
            self::assertTrue($prepared->execute([150]));
            self::assertSame([3], $prepared->fetchAll(\PDO::FETCH_COLUMN));

            $joined = $ztdPdo->query('SELECT id, label FROM account_labels ORDER BY id');
            self::assertNotFalse($joined);
            self::assertSame(
                [['id' => 1, 'label' => 'North'], ['id' => 3, 'label' => 'North']],
                $joined->fetchAll(),
            );

            $aggregate = $ztdPdo->query('SELECT region, CAST(account_count AS SIGNED) AS account_count, CAST(total_amount AS SIGNED) AS total_amount FROM region_totals');
            self::assertNotFalse($aggregate);
            self::assertSame(
                [['region' => 'north', 'account_count' => 2, 'total_amount' => 400]],
                $aggregate->fetchAll(),
            );
        } finally {
            $pdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
