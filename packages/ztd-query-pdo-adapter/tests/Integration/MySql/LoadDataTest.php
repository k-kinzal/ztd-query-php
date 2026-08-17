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
final class LoadDataTest extends TestCase
{
    public function testLoadDataVariantsMutateOnlyTheShadowTable(): void
    {
        [$databaseName, $pdo] = MySqlContainer::createTestDatabase();
        $table = 'load_' . bin2hex(random_bytes(8));

        try {
            $pdo->exec(sprintf(
                'CREATE TABLE `%s` (id INT PRIMARY KEY, name VARCHAR(100) DEFAULT \'unknown\')',
                $table,
            ));
            $ztdPdo = ZtdPdo::fromPdo($pdo);

            $initial = tmpfile();
            self::assertIsResource($initial);
            $initialData = "id,name\r\n>1,\"Alice, A\"\r\n>2,\\N\r\n";
            self::assertSame(strlen($initialData), fwrite($initial, $initialData));
            $initialMetadata = stream_get_meta_data($initial);
            $initialPath = $initialMetadata['uri'] ?? null;
            self::assertIsString($initialPath);
            $initialSql = sprintf(
                "LOAD DATA LOCAL INFILE '%s' INTO TABLE `%s` "
                . "FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"' ESCAPED BY '\\\\' "
                . "LINES STARTING BY '>' TERMINATED BY '\\r\\n' IGNORE 1 LINES (id, @raw) "
                . "SET name = COALESCE(@raw, 'unknown')",
                str_replace("'", "''", $initialPath),
                $table,
            );
            self::assertSame(2, $ztdPdo->exec($initialSql));

            $replacement = tmpfile();
            self::assertIsResource($replacement);
            self::assertSame(14, fwrite($replacement, "2\tBob\n3\tCarol\n"));
            $replacementMetadata = stream_get_meta_data($replacement);
            $replacementPath = $replacementMetadata['uri'] ?? null;
            self::assertIsString($replacementPath);
            self::assertSame(2, $ztdPdo->exec(sprintf(
                "LOAD DATA INFILE '%s' REPLACE INTO TABLE `%s`",
                str_replace("'", "''", $replacementPath),
                $table,
            )));

            $ignored = tmpfile();
            self::assertIsResource($ignored);
            self::assertSame(17, fwrite($ignored, "3\tIgnored\n4\tDave\n"));
            $ignoredMetadata = stream_get_meta_data($ignored);
            $ignoredPath = $ignoredMetadata['uri'] ?? null;
            self::assertIsString($ignoredPath);
            self::assertSame(1, $ztdPdo->exec(sprintf(
                "LOAD DATA INFILE '%s' IGNORE INTO TABLE `%s`",
                str_replace("'", "''", $ignoredPath),
                $table,
            )));

            $rows = $ztdPdo->query(sprintf('SELECT id, name FROM `%s` ORDER BY id', $table));
            self::assertNotFalse($rows);
            self::assertSame([
                ['id' => 1, 'name' => 'Alice, A'],
                ['id' => 2, 'name' => 'Bob'],
                ['id' => 3, 'name' => 'Carol'],
                ['id' => 4, 'name' => 'Dave'],
            ], $rows->fetchAll());

            $physical = $pdo->query(sprintf('SELECT COUNT(*) FROM `%s`', $table));
            self::assertNotFalse($physical);
            self::assertSame(0, (int) $physical->fetchColumn());
        } finally {
            $pdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
