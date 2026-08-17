<?php

declare(strict_types=1);

namespace Tests\Integration\MySql;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\MySqlContainer;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

#[CoversNothing]
#[Large]
final class CaseWhereTest extends TestCase
{
    public function testUpdateAndDeleteRestrictRowsWithCaseExpression(): void
    {
        [$databaseName, $rawPdo] = MySqlContainer::createTestDatabase();
        $updates = 'prefix_' . bin2hex(random_bytes(8));
        $deletes = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE `{$updates}` (id INT PRIMARY KEY, score INT)");
            $rawPdo->exec("CREATE TABLE `{$deletes}` (id INT PRIMARY KEY, score INT)");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $rows = 'VALUES (1, 85), (2, 60), (3, 95), (4, 45)';
            $ztdPdo->exec("INSERT INTO `{$updates}` {$rows}");
            $ztdPdo->exec("INSERT INTO `{$deletes}` {$rows}");

            self::assertSame(2, $ztdPdo->exec("UPDATE `{$updates}` SET score = 0 WHERE CASE WHEN score > 80 THEN 1 ELSE 0 END = 1"));
            self::assertSame(2, $ztdPdo->exec("DELETE FROM `{$deletes}` WHERE CASE WHEN score > 80 THEN 1 ELSE 0 END = 1"));

            $updated = $ztdPdo->query("SELECT id, score FROM `{$updates}` ORDER BY id");
            $remaining = $ztdPdo->query("SELECT id, score FROM `{$deletes}` ORDER BY id");
            self::assertNotFalse($updated);
            self::assertNotFalse($remaining);
            self::assertSame([
                ['id' => 1, 'score' => 0],
                ['id' => 2, 'score' => 60],
                ['id' => 3, 'score' => 0],
                ['id' => 4, 'score' => 45],
            ], $updated->fetchAll(PDO::FETCH_ASSOC));
            self::assertSame([
                ['id' => 2, 'score' => 60],
                ['id' => 4, 'score' => 45],
            ], $remaining->fetchAll(PDO::FETCH_ASSOC));

            $physicalUpdates = $rawPdo->query("SELECT * FROM `{$updates}`");
            $physicalDeletes = $rawPdo->query("SELECT * FROM `{$deletes}`");
            self::assertNotFalse($physicalUpdates);
            self::assertNotFalse($physicalDeletes);
            self::assertSame([], $physicalUpdates->fetchAll(PDO::FETCH_ASSOC));
            self::assertSame([], $physicalDeletes->fetchAll(PDO::FETCH_ASSOC));
        } finally {
            $rawPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
