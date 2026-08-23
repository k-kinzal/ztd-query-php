<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSql;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\PostgreSqlContainer;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

#[CoversNothing]
#[Large]
final class PreparedExecutionTest extends TestCase
{
    public function testNativePositionsRemainBoundAcrossExpressionsAndMutations(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $products = 'products_' . bin2hex(random_bytes(8));
        $categories = 'categories_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$categories} (category_id INTEGER PRIMARY KEY, name TEXT)");
            $rawPdo->exec("CREATE TABLE {$products} (id INTEGER PRIMARY KEY, category_id INTEGER, name TEXT, price INTEGER, status TEXT)");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $insertCategory = $ztdPdo->prepare("INSERT INTO {$categories} VALUES (\$1, \$2)");
            self::assertInstanceOf(\PDOStatement::class, $insertCategory);
            self::assertTrue($insertCategory->execute([1, 'tools']));

            $insertProduct = $ztdPdo->prepare("INSERT INTO {$products} VALUES (\$1, \$2, \$3, \$4, \$5)");
            self::assertInstanceOf(\PDOStatement::class, $insertProduct);
            self::assertTrue($insertProduct->execute([1, 1, 'Hammer', 20, 'active']));
            self::assertTrue($insertProduct->execute([2, 1, 'Saw', 40, 'active']));

            $aggregate = $ztdPdo->prepare("SELECT category_id, SUM(price) FILTER (WHERE status = \$1) AS total FROM {$products} GROUP BY category_id HAVING SUM(price) > \$2");
            self::assertInstanceOf(\PDOStatement::class, $aggregate);
            self::assertTrue($aggregate->execute(['active', 50]));
            self::assertSame(60, $aggregate->fetchColumn(1));

            $joined = $ztdPdo->prepare("SELECT p.name FROM {$products} p JOIN {$categories} c USING (category_id) WHERE p.price BETWEEN \$1 AND \$2 ORDER BY p.id");
            self::assertInstanceOf(\PDOStatement::class, $joined);
            self::assertTrue($joined->execute([10, 30]));
            self::assertSame(['Hammer'], $joined->fetchAll(PDO::FETCH_COLUMN));

            $update = $ztdPdo->prepare("UPDATE {$products} SET status = CASE WHEN price >= \$1 THEN \$2 ELSE status END WHERE id IN (\$3, \$4)");
            self::assertInstanceOf(\PDOStatement::class, $update);
            self::assertTrue($update->execute([30, 'premium', 1, 2]));

            $current = $ztdPdo->prepare("SELECT status FROM {$products} WHERE id = \$1");
            self::assertInstanceOf(\PDOStatement::class, $current);
            self::assertTrue($current->execute([2]));
            self::assertSame('premium', $current->fetchColumn());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
